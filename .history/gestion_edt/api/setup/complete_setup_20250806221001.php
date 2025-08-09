<?php
/**
 * API pour Finaliser la Configuration Initiale
 */

// On inclut les fichiers nécessaires au début
require_once '../auth/session_check_setup.php'; 
require_once '../../config/database.php';
require_once '../../includes/functions.php'; // On inclut le fichier qui contient getFormattedName()

header('Content-Type: application/json');

// Récupère les données JSON envoyées par le JavaScript
$data = json_decode(file_get_contents("php://input"), true);

// On récupère les ID depuis la session et le gardien
$utilisateur_id = $_SESSION['utilisateur_id'];
// $etablissement_id est maintenant disponible grâce à session_check_setup.php

// Validation des données
if (empty($data['excelData']) || empty($data['formateursData']) || !isset($data['espaces'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Des données de configuration essentielles sont manquantes.']);
    exit;
}

// Récupération des données
$rawExcelDataWithHeader = $data['excelData'];
$formateursData = $data['formateursData']; // Les données vérifiées par l'utilisateur
$holidays = $data['holidays'] ?? [];
$vacations = $data['vacations'] ?? [];
$espaces = $data['espaces'];

if (empty($rawExcelDataWithHeader) || !isset($rawExcelDataWithHeader[0])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Les données Excel reçues sont vides.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // --- 1. Sauvegarde des données des formateurs (celles validées par le directeur) ---
    // On supprime l'ancienne entrée de type 'formateur'
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'formateur'")
        ->execute([$etablissement_id]);
    // On insère la nouvelle liste d'objets (nom, matricule, email, masse_horaire)
    $stmtFormateurs = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'formateur', ?)");
    $stmtFormateurs->execute([$etablissement_id, json_encode($formateursData)]);
    
    // --- 2. Extraction du reste des données (Groupes, Affectations) depuis l'Excel ---
    $groupes = []; $fusionGroupes = []; $affectations = [];
    
    $dataRows = array_slice($rawExcelDataWithHeader, 1);

    // Définition fixe des index de colonnes
    $groupeIndex = 8;
    $fusionGroupeIndex = 12;
    $moduleIndex = 16;
    $nomFormateurIndex = 20;
    $nomFormateurSyncIndex = 22;

    // NOTE : La fonction getFormattedName n'est plus définie ici. Elle est incluse depuis functions.php
    
    foreach ($dataRows as $row) {
        $groupe = trim($row[$groupeIndex] ?? '');
        if ($groupe) $groupes[] = $groupe;
        
        $fusionGroupe = trim($row[$fusionGroupeIndex] ?? '');
        if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;
        
        $formateurP = getFormattedName($row[$nomFormateurIndex] ?? null);
        $formateurS = getFormattedName($row[$nomFormateurSyncIndex] ?? null);
        $module = trim($row[$moduleIndex] ?? '');

        if ($formateurP && $groupe && $module) $affectations[] = ['formateur' => $formateurP, 'groupe' => $groupe, 'module' => $module, 'type' => 'presentiel'];
        if ($formateurS && $fusionGroupe && $module) $affectations[] = ['formateur' => $formateurS, 'groupe' => $fusionGroupe, 'module' => $module, 'type' => 'synchrone'];
    }
    
    $groupesList = array_values(array_unique($groupes)); sort($groupesList);
    $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);
    
    // On supprime les anciennes données volatiles avant de les réinsérer
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee IN ('groupe', 'fusion_groupe', 'affectation')")->execute([$etablissement_id]);
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    if(!empty($groupesList)) $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    if(!empty($fusionGroupesList)) $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
    if(!empty($affectations)) $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);

    // --- 3. Sauvegarde des données d'avancement ---
    $fileName = "Base initiale du " . date('d-m-Y');
    $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)");
    $avancementStmt->execute([$etablissement_id, $fileName, json_encode($dataRows)]);

    // --- 4. Sauvegarde des données du calendrier (CORRIGÉ) ---
    $calendarData = ['holidays' => $holidays, 'vacations' => $vacations];
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'calendrier'")->execute([$etablissement_id]);
    $stmtCalendar = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'calendrier', ?)");
    $stmtCalendar->execute([$etablissement_id, json_encode($calendarData)]);

    // --- 5. Sauvegarde des espaces (CORRIGÉ) ---
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
    
    // --- 7. FINALISER LA SESSION ---
    $_SESSION['etablissement_id'] = $etablissement_id;

    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Erreur lors de la finalisation: " . $e->getMessage()]);
}
?>