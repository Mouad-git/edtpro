<?php
/**
 * API pour Finaliser la Configuration Initiale
 *
 * Ce script reçoit les données consolidées des 4 étapes du formulaire de configuration :
 * 1. Les données brutes du fichier Excel.
 * 2. Les informations vérifiées des formateurs (masse horaire, email, matricule).
 * 3. Les jours fériés et les périodes de vacances.
 * 4. La liste des espaces/salles.
 *
 * Il effectue une transaction pour sauvegarder toutes ces informations de manière atomique.
 */

// On inclut le gardien (qui vérifie 'utilisateur_id') et la connexion BDD
require_once '../auth/session_check.php';
require_once '../../config/database.php';

// On indique que la réponse sera au format JSON
header('Content-Type: application/json');

// Récupère les données JSON envoyées par le JavaScript
$data = json_decode(file_get_contents("php://input"), true);

// On récupère les ID depuis la session (sécurisé)
$etablissement_id = $_SESSION['etablissement_id'];
$utilisateur_id = $_SESSION['utilisateur_id'];

// Validation simple pour s'assurer que les données principales sont là
if (empty($data['excelData']) || empty($data['formateursData']) || empty($data['espaces'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Des données de configuration essentielles sont manquantes.']);
    exit;
}

// Récupération et nettoyage des données
$rawExcelData = $data['excelData'];
$formateursData = $data['formateursData'];
$holidays = $data['holidays'] ?? [];
$vacations = $data['vacations'] ?? [];
$espaces = $data['espaces'];

try {
    // On utilise une transaction. Si une seule requête échoue, tout est annulé.
    $pdo->beginTransaction();

    // --- 1. Sauvegarde des données des formateurs ---
    // On supprime les anciennes données de type 'formateur' pour cet établissement avant d'insérer les nouvelles.
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'formateur'")
        ->execute([$etablissement_id]);
    // On insère la nouvelle liste d'objets formateurs (avec nom, email, matricule, etc.)
    $stmtFormateurs = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'formateur', ?)");
    $stmtFormateurs->execute([$etablissement_id, json_encode($formateursData)]);
    
    // --- 2. Extraction et sauvegarde du reste des données de l'Excel ---
    // (Cette logique est inspirée de votre ancien script `upload_base_data.php`)
    $groupes = []; $fusionGroupes = []; $affectations = [];
    
    // On cherche les index des colonnes dynamiquement
    $header = array_map('trim', array_shift($rawExcelData));
    $nomFormateurIndex = array_search('Formateur Affecté Présentiel Actif', $header);
    $nomFormateurSyncIndex = array_search('Formateur Affecté Synchrone Actif', $header); // Assurez-vous que ce titre est correct
    $groupeIndex = array_search('Groupe', $header);
    $fusionGroupeIndex = array_search('Groupe Fusion Synchrone', $header);
    $moduleIndex = array_search('Module', $header);

    // Votre fonction de formatage de nom
    function getFormattedName($name) { /* ... Votre fonction getFormattedName ... */ 
    }
    
    foreach ($rawExcelData as $row) {
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
    
    // On insère les groupes, fusions, et affectations
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    if(!empty($groupesList)) $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    if(!empty($fusionGroupesList)) $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
    if(!empty($affectations)) $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);

    // --- 3. Sauvegarde des données d'avancement ---
    $fileName = "Base initiale du " . date('d-m-Y');
    $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)");
    $avancementStmt->execute([$etablissement_id, $fileName, json_encode($rawExcelData)]);

    // --- 4. Sauvegarde des données de calendrier ---
    $calendarData = ['holidays' => $holidays, 'vacations' => $vacations];
    $stmtCalendar = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'calendrier', ?)");
    $stmtCalendar->execute([$etablissement_id, json_encode($calendarData)]);

    // --- 5. Sauvegarde des espaces ---
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

    // Si on arrive ici, tout s'est bien passé. On valide toutes les opérations.
    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Si une erreur est survenue, on annule toutes les opérations.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>