<?php
// api/setup/complete_setup.php
require_once '../auth/session_check.php';
require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"), true);

// Validation des données reçues
if (!isset($data['excelData']) || !isset($data['formateursData'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
    exit;
}

$etablissement_id = $_SESSION['etablissement_id'];
$utilisateur_id = $_SESSION['utilisateur_id'];
$rawExcelData = $data['excelData'];
$formateursDetailedData = $data['formateursData']; // Les données détaillées du formulaire

try {
    $pdo->beginTransaction();

    // --- NOUVELLE LOGIQUE : Préparation des données ---

    // 1. On crée la liste simple des NOMS de formateurs pour admin.html
    $formateursNomsList = [];
    foreach ($formateursDetailedData as $formateur) {
        // On met le nom en majuscules pour la cohérence
        $formateursNomsList[] = mb_strtoupper($formateur['nom'], 'UTF-8');
    }
    sort($formateursNomsList); // On trie la liste des noms

    // 2. On met à jour les e-mails en majuscules dans les données détaillées
    foreach ($formateursDetailedData as &$formateur) { // Notez le '&' pour modifier le tableau original
        if (isset($formateur['email'])) {
            $formateur['email'] = mb_strtoupper($formateur['email'], 'UTF-8');
        }
    }
    unset($formateur); // Bonnes pratiques : supprimer la référence

    // --- FIN DE LA NOUVELLE LOGIQUE ---


    // 3. Sauvegarder les données des formateurs (maintenant avec une distinction)
    // On vide les anciennes données de ce type
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee IN ('formateur', 'formateurs_details')")
        ->execute([$etablissement_id]);

    $stmtInsert = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    
    // a) On insère la liste SIMPLE des noms pour admin.html
    $stmtInsert->execute([$etablissement_id, 'formateur', json_encode($formateursNomsList)]);
    
    // b) On insère les données DÉTAILLÉES pour un usage futur (ex: page de gestion des formateurs)
    $stmtInsert->execute([$etablissement_id, 'formateurs_details', json_encode($formateursDetailedData)]);
    
    
    // 4. Extraire et sauvegarder le reste des données de l'Excel (groupes, affectations...)
    $groupes = []; $fusionGroupes = []; $affectations = [];
    function getFormattedName($name) {
        if (!$name || !is_string($name)) return '';
        return mb_strtoupper(trim($name), 'UTF-8');
    }

    $header = array_shift($rawExcelData);
    $groupeIndex = array_search('Groupe', $header);
    $fusionGroupeIndex = array_search('Regroupement', $header);
    $moduleIndex = array_search('Module', $header);
    $formateurPIndex = array_search('Formateur Affecté Présentiel Actif', $header);
    $formateurSIndex = array_search('Formateur Affecté Synchrone Actif', $header);

    // ... (Ajoutez des vérifications pour chaque index si nécessaire)

    foreach ($rawExcelData as $row) {
        $groupe = trim($row[$groupeIndex] ?? '');
        $fusionGroupe = trim($row[$fusionGroupeIndex] ?? '');
        $module = trim($row[$moduleIndex] ?? '');
        $formateurP = getFormattedName($row[$formateurPIndex] ?? null);
        $formateurS = getFormattedName($row[$formateurSIndex] ?? null);

        if ($groupe) $groupes[] = $groupe;
        if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;

        if ($formateurP && $groupe && $module) $affectations[] = ['formateur' => $formateurP, 'groupe' => $groupe, 'module' => $module, 'type' => 'presentiel'];
        if ($formateurS && $fusionGroupe && $module) $affectations[] = ['formateur' => $formateurS, 'groupe' => $fusionGroupe, 'module' => $module, 'type' => 'synchrone'];
    }
    
    $groupesList = array_values(array_unique($groupes)); sort($groupesList);
    $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);

    if(!empty($groupesList)) $stmtInsert->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    if(!empty($fusionGroupesList)) $stmtInsert->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
    if(!empty($affectations)) $stmtInsert->execute([$etablissement_id, 'affectation', json_encode($affectations)]);

    // 5. Sauvegarder les données d'avancement
    $fileName = "Base initiale";
    $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)");
    $avancementStmt->execute([$etablissement_id, $fileName, json_encode($rawExcelData)]);

    // 6. Marquer la configuration comme terminée pour l'utilisateur
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