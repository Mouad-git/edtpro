<?php
// api/setup/complete_setup.php
require_once '../auth/session_check.php';
require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);

$etablissement_id = $_SESSION['etablissement_id'];
$utilisateur_id = $_SESSION['utilisateur_id'];
$rawExcelData = $data['excelData'];
$formateursData = $data['formateursData'];

try {
    $pdo->beginTransaction();

    // 1. Sauvegarder les données des formateurs (avec masse horaire, email, matricule)
    // Au lieu d'une liste de noms, on stocke une liste d'objets
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'formateur'")
        ->execute([$etablissement_id]);
    $stmtFormateurs = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'formateur', ?)");
    $stmtFormateurs->execute([$etablissement_id, json_encode($formateursData)]);
    
    // 2. Extraire et sauvegarder le reste des données de l'Excel (groupes, affectations...)
    // (Ici, vous réutilisez la logique d'extraction de votre ancien `upload_base_data.php`)
    $groupes = []; $fusionGroupes = []; $affectations = [];
    // ... boucle foreach sur $rawExcelData pour remplir ces tableaux ...
    
    $groupesList = array_values(array_unique($groupes));
    // ...
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    if(!empty($groupesList)) $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    // ... etc. pour fusion_groupe et affectation ...

    // 3. Sauvegarder les données d'avancement
    $fileName = "Base initiale"; // ou un autre nom
    $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)");
    $avancementStmt->execute([$etablissement_id, $fileName, json_encode($rawExcelData)]);

    // 4. Marquer la configuration comme terminée pour l'utilisateur
    $stmtSetup = $pdo->prepare("UPDATE utilisateurs SET is_setup_complete = TRUE WHERE id = ?");
    $stmtSetup->execute([$utilisateur_id]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>