<?php
// api/data/upload_base_data.php

require_once '../auth/session_check.php';
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

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
        throw new Exception("La feuille 'AvancementProgramme' est introuvable. Veuillez vérifier le nom de la feuille dans votre fichier Excel.");
    }
    $rowsWithHeader = $sheet->toArray(null, true, true, true);
    
    // On retire la ligne d'en-tête pour ne pas la traiter comme une donnée
    $header = array_shift($rowsWithHeader); 
    $rows = $rowsWithHeader;

    if (empty($rows)) {
        throw new Exception("Le fichier Excel ne contient aucune ligne de données après l'en-tête.");
    }
    
    $formateurs = []; $groupes = []; $fusionGroupes = []; $affectations = [];

    // --- VOTRE FONCTION getFormattedName ---
    function getFormattedName($name) {
        if (!$name || !is_string($name)) return '';
        $words = array_filter(preg_split('/\s+/', trim($name)));
        if (count($words) === 0) return '';
        if (count($words) <= 1) return $name;
        if (count($words) === 2) return $words[1];
        $avantDernier = $words[count($words) - 2];
        $dernier = $words[count($words) - 1];
        return mb_strlen($avantDernier) < 4 ? "$avantDernier $dernier" : $dernier;
    }

    foreach ($rows as $rowNumber => $row) {
        // IMPORTANT : Vérifiez que les lettres des colonnes sont correctes pour votre fichier
        $formateurP = getFormattedName($row['U'] ?? null);     // Colonne U
        $formateurS = getFormattedName($row['W'] ?? null);     // Colonne W
        $groupe = trim($row['I'] ?? '');                       // Colonne I
        $fusionGroupe = trim($row['M'] ?? '');                 // Colonne M
        $module = trim($row['Q'] ?? '');                       // Colonne Q

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
    
    // --- AMÉLIORATION CRUCIALE : On vérifie si on a trouvé quelque chose ---
    if (empty($formateursList) && empty($groupesList)) {
        throw new Exception("Aucun formateur ou groupe n'a été trouvé dans le fichier. Vérifiez que les colonnes I, U et W sont correctement remplies.");
    }
    
    // --- Transaction de base de données (inchangée) ---
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ?")->execute([$etablissement_id]);
    
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    if(!empty($formateursList)) $insertStmt->execute([$etablissement_id, 'formateur', json_encode($formateursList)]);
    if(!empty($groupesList)) $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    if(!empty($fusionGroupesList)) $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
    if(!empty($affectations)) $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);

    $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)");
    $avancementStmt->execute([$etablissement_id, $fileName, json_encode($rows)]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    // On renvoie un message d'erreur clair au frontend
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>