<?php
// api/setup/complete_setup.php (Version Finale et Corrigée)

require_once '../auth/session_check_setup.php';
// La connexion BDD est déjà faite par le gardien.
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$utilisateur_id = $_SESSION['utilisateur_id'];
$etablissement_id = $_SESSION['etablissement_id'];

// Validation des données reçues
if (empty($data['excelData']) || empty($data['formateursData']) || empty($data['espaces'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Des données de configuration essentielles sont manquantes.']);
    exit;
}

$rawExcelDataWithHeader = $data['excelData'];
$formateursData = $data['formateursData'];
$holidays = $data['holidays'] ?? [];
$vacations = $data['vacations'] ?? [];
$espaces = $data['espaces'];

try {
    $pdo->beginTransaction();

    // --- 1. Sauvegarde des données des formateurs (vérifiées par l'utilisateur) ---
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'formateur'")->execute([$etablissement_id]);
    $stmtFormateurs = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'formateur', ?)");
    $stmtFormateurs->execute([$etablissement_id, json_encode($formateursData)]);
    
    // --- 2. Extraction du reste des données depuis l'Excel ---
    $fusionGroupes = []; 
    $affectations = [];
    
    $header = array_map('trim', $rawExcelDataWithHeader[0]);
    $dataRows = array_slice($rawExcelDataWithHeader, 1);
    
    // Définition des index de colonnes
    $groupeIndex = 8;        // Colonne I
    $modeIndex = 15;         // Colonne P
    $fusionGroupeIndex = 12; // Colonne M
    $moduleIndex = 16;       // Colonne Q
    $nomFormateurIndex = 20; // Colonne U
    $nomFormateurSyncIndex = 22; // Colonne W

    $groupesMap = [];

    foreach ($dataRows as $row) {
        $groupe = trim($row[$groupeIndex] ?? '');
        $mode = trim($row[$modeIndex] ?? 'Résidentiel');
        
        if ($groupe && !isset($groupesMap[$groupe])) {
            $modeNormalise = (stripos($mode, 'Alterné') !== false) ? 'Alterné' : 'Résidentiel';
            $groupesMap[$groupe] = ['nom' => $groupe, 'mode' => $modeNormalise];
        }
        
        $fusionGroupe = trim($row[$fusionGroupeIndex] ?? '');
        if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;
        
        $formateurP = getFormattedName($row[$nomFormateurIndex] ?? null);
        $formateurS = getFormattedName($row[$nomFormateurSyncIndex] ?? null);
        $module = trim($row[$moduleIndex] ?? '');

        if ($formateurP && $groupe && $module) $affectations[] = ['formateur' => $formateurP, 'groupe' => $groupe, 'module' => $module, 'type' => 'presentiel'];
        if ($formateurS && $fusionGroupe && $module) $affectations[] = ['formateur' => $formateurS, 'groupe' => $fusionGroupe, 'module' => $module, 'type' => 'synchrone'];
    }
    
    $groupesListAvecMode = array_values($groupesMap); // C'est la liste d'objets
    $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);
    
    // --- CORRECTION MAJEURE : On supprime toutes les anciennes données volatiles ---
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee IN ('groupe', 'fusion_groupe', 'affectation', 'groupe_mode')")
        ->execute([$etablissement_id]);
    
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    
    // On sauvegarde la nouvelle structure de données (objets) pour les groupes
    if(!empty($groupesListAvecMode)) {
        $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesListAvecMode)]);
    }
    // On sauvegarde les autres données
    if(!empty($fusionGroupesList)) $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
    if(!empty($affectations)) $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);

    // --- 3, 4, 5, 6 : Les autres sauvegardes restent identiques ---
    $fileName = "Base initiale du " . date('d-m-Y');
    $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)");
    $avancementStmt->execute([$etablissement_id, $fileName, json_encode($dataRows)]);

    $calendarData = ['holidays' => $holidays, 'vacations' => $vacations];
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'calendrier'")->execute([$etablissement_id]);
    $stmtCalendar = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'calendrier', ?)");
    $stmtCalendar->execute([$etablissement_id, json_encode($calendarData)]);

    $pdo->prepare("DELETE FROM espaces WHERE etablissement_id = ?")->execute([$etablissement_id]);
    $stmtEspaces = $pdo->prepare("INSERT INTO espaces (etablissement_id, nom_espace) VALUES (?, ?)");
    foreach ($espaces as $espace) {
        if (!empty(trim($espace))) {
            $stmtEspaces->execute([$etablissement_id, trim($espace)]);
        }
    }

    $stmtSetup = $pdo->prepare("UPDATE utilisateurs SET is_setup_complete = TRUE WHERE id = ?");
    $stmtSetup->execute([$utilisateur_id]);
    
    $_SESSION['etablissement_id'] = $etablissement_id;
    
    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function getFormattedName($name) {
    if (!$name || !is_string($name)) return '';
    $words = array_filter(preg_split('/\s+/', trim(strtoupper($name))));
    $wordCount = count($words);
    if ($wordCount === 0) return '';
    if ($wordCount <= 1) return $words[0];
    if ($wordCount >= 3 && mb_strlen($words[1]) <= 3) return $words[1] . ' ' . $words[2];
    $dernierMot = $words[$wordCount - 1];
    if (mb_strlen($dernierMot) <= 2 && $wordCount > 1) return $words[$wordCount - 2] . ' ' . $dernierMot;
    return $dernierMot;
}
?>