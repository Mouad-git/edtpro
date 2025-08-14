<?php
// api/setup/complete_setup.php

require_once '../auth/session_check_setup.php'; // Gardien allégé
// La connexion $pdo est déjà incluse par le gardien.

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$utilisateur_id = $_SESSION['utilisateur_id'];
$etablissement_id = $_SESSION['etablissement_id'];

// Validation des données
if (empty($data['excelData']) || empty($data['formateursData']) || !isset($data['espaces'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Des données de configuration essentielles sont manquantes.']);
    exit;
}

$rawExcelDataWithHeader = $data['excelData'];
$formateursData = $data['formateursData'];
$holidays = $data['holidays'] ?? [];
$vacations = $data['vacations'] ?? [];
$espaces = $data['espaces'];

if (empty($rawExcelDataWithHeader) || !isset($rawExcelDataWithHeader[0])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Les données Excel sont vides ou mal formatées.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // --- 1. Sauvegarde des données des formateurs (vérifiées par l'utilisateur) ---
    // On sauvegarde la liste complète des objets formateurs dans donnees_de_base.
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'formateur'")->execute([$etablissement_id]);
    $stmtFormateurs = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'formateur', ?)");
    $stmtFormateurs->execute([$etablissement_id, json_encode($formateursData)]);
    
    // --- 2. Extraction du reste des données de l'Excel ---
    $groupes = []; $fusionGroupes = []; $affectations = [];
    $groupesMap = [];
    
    $header = array_map('trim', $rawExcelDataWithHeader[0]);
    $dataRows = array_slice($rawExcelDataWithHeader, 1);
    
    // Définition des index de colonnes
    $groupeIndex = 8; $modeIndex = 15; $fusionGroupeIndex = 12;
    $moduleIndex = 16; $nomFormateurIndex = 20; $nomFormateurSyncIndex = 22;

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
    
    $groupesList = array_values($groupesMap);
    $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);
    
    // On nettoie et on insère les données volatiles dans donnees_de_base
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee IN ('groupe', 'fusion_groupe', 'affectation')")->execute([$etablissement_id]);
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    if(!empty($groupesList)) $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    if(!empty($fusionGroupesList)) $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
    if(!empty($affectations)) $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);

    // --- 3. Sauvegarde des données d'avancement ---
    $fileName = "Base initiale du " . date('d-m-Y');
    $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)");
    $avancementStmt->execute([$etablissement_id, $fileName, json_encode($dataRows)]);

    // --- 4. Sauvegarde des données du calendrier (DANS LA BONNE TABLE) ---
    $calendarData = ['holidays' => $holidays, 'vacations' => $vacations];
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'calendrier'")->execute([$etablissement_id]);
    $stmtCalendar = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'calendrier', ?)");
    $stmtCalendar->execute([$etablissement_id, json_encode($calendarData)]);

    // --- 5. Sauvegarde des espaces (DANS LA BONNE TABLE) ---
    $pdo->prepare("DELETE FROM espaces WHERE etablissement_id = ?")->execute([$etablissement_id]);
    $stmtEspaces = $pdo->prepare("INSERT INTO espaces (etablissement_id, nom_espace) VALUES (?, ?)");
    foreach ($espaces as $espace) {
        if (!empty(trim($espace))) {
            $stmtEspaces->execute([$etablissement_id, trim($espace)]);
        }
    }

    // --- 6. Marquer la configuration comme terminée ---
    $stmtSetup = $pdo->prepare("UPDATE utilisateurs SET is_setup_complete = TRUE WHERE id = ?");
    $stmtSetup->execute([$utilisateur_id]);
    
    // --- 7. Finaliser la session ---
    $_SESSION['etablissement_id'] = $etablissement_id;

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Erreur lors de la finalisation: " . $e->getMessage()]);
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