<?php
    // api/data/upload_base_data.php
    // NOUVELLE VERSION ROBUSTE
require_once __DIR__ . '/../auth/session_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

    use PhpOffice\PhpSpreadsheet\IOFactory;

    $etablissement_id = $_SESSION['etablissement_id'];

    if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload du fichier.']);
        exit;
    }

    $filePath = $_FILES['excelFile']['tmp_name'];
    $fileName = basename($_FILES['excelFile']['name']);

    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName('AvancementProgramme');
        if (!$sheet) {
            throw new Exception("La feuille 'AvancementProgramme' est introuvable.");
        }
        $rows = $sheet->toArray(null, true, true, true);

        // --- Logique d'extraction (similaire à votre code précédent) ---
        if (isset($rows[1]['A']) && $rows[1]['A'] === "Date MAJ") array_shift($rows);
        
        $formateurs = []; $groupes = []; $fusionGroupes = []; $affectations = [];

// Votre fonction getFormattedName reste la même
function getFormattedName($name) {
    if (!$name || !is_string($name)) return '';
    $words = preg_split('/\s+/', trim($name));
    if (count($words) <= 1) return $name;
    if (count($words) === 2) return $words[1];
    $avantDernier = $words[count($words) - 2];
    $dernier = $words[count($words) - 1];
    return strlen($avantDernier) < 4 ? "$avantDernier $dernier" : $dernier;
}

// On saute la première ligne (en-têtes)
array_shift($rows); 

foreach ($rows as $row) {
    // On vérifie si la ligne n'est pas complètement vide
    if (empty(array_filter($row))) {
        continue;
    }

    $formateurP = getFormattedName($row[20]); // Indice pour la colonne U
    $formateurS = getFormattedName($row[22]); // Indice pour la colonne W
    $groupe = trim($row[8]);                  // Indice pour la colonne I
    $fusionGroupe = trim($row[12]);           // Indice pour la colonne M
    $module = trim($row[16]);                 // Indice pour la colonne Q

    if ($formateurP) $formateurs[] = $formateurP;
    if ($formateurS) $formateurs[] = $formateurS;
    if ($groupe) $groupes[] = $groupe;
    if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;

    if ($formateurP && $groupe && $module) $affectations[] = ['formateur' => $formateurP, 'groupe' => $groupe, 'module' => $module, 'type' => 'presentiel'];
    if ($formateurS && $fusionGroupe && $module) $affectations[] = ['formateur' => $formateurS, 'groupe' => $fusionGroupe, 'module' => $module, 'type' => 'synchrone'];
}
        

        $formateursList = array_values(array_unique($formateurs)); sort($formateursList);
        $groupesList = array_values(array_unique($groupes)); sort($groupesList);
        $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);

        header('Content-Type: application/json'); // Pour que la réponse reste lisible
echo json_encode([
    'debug_message' => 'Contenu extrait du fichier Excel',
    'formateurs_trouves' => $formateursList,
    'groupes_trouves' => $groupesList,
    'affectations_trouvees' => count($affectations)
]);
exit;
        
        // --- Transaction de base de données ---
        $pdo->beginTransaction();

        // On vide les anciennes données pour cet établissement
        $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ?")->execute([$etablissement_id]);

        // On insère les nouvelles
        $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
        $insertStmt->execute([$etablissement_id, 'formateur', json_encode($formateursList)]);
        $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
        $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
        $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);

        // On met à jour la table d'avancement
        $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)");
        $avancementStmt->execute([$etablissement_id, $fileName, json_encode($rows)]);

        $pdo->commit();

        echo json_encode(['success' => true]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    ?>