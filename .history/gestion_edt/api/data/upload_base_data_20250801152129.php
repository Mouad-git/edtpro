<?php
// api/data/upload_base_data.php

require_once '../auth/session_check.php';
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

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
    
    // On lit avec les index numériques pour plus de robustesse
    $allRows = $sheet->toArray(null, true, false, true);
    $header = array_map('trim', array_shift($allRows));
    $dataRows = $allRows;

    // --- Détection des colonnes par leur nom ---
    $groupeIndex = array_search('Groupe', $header);
    $fusionGroupeIndex = array_search('FusionGroupe', $header);
    $moduleIndex = array_search('Code Module', $header);
    $nomFormateurIndex = array_search('Formateur Affecté Présentiel Actif', $header);
    $nomFormateurSyncIndex = array_search('Formateur Affecté Syn Actif', $header);
    $estRegionalIndex = array_search('EST_REGIONAL', $header); // Assurez-vous que le titre est EXACTEMENT "EST_REGIONAL"
    $s1HeuresIndex = array_search('MH affectée S1', $header);  // Assurez-vous que le titre est EXACTEMENT "MH affectée S1"
    $s2HeuresIndex = array_search('MH affectée S2', $header);  // Assurez-vous que le titre est EXACTEMENT "MH affectée S2"

    // Validation des colonnes essentielles
    if ($groupeIndex === false || $moduleIndex === false || $nomFormateurIndex === false || $estRegionalIndex === false || $s1HeuresIndex === false || $s2HeuresIndex === false) {
        throw new Exception("Une ou plusieurs colonnes requises sont manquantes dans l'en-tête du fichier Excel (Groupe, Code Module, Formateur Affecté..., EST_REGIONAL, MH affectée S1/S2).");
    }

    $groupes = []; $fusionGroupes = []; $affectations = [];

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

    foreach ($dataRows as $row) {
        $groupe = trim($row[$groupeIndex] ?? '');
        if ($groupe) $groupes[] = $groupe;
        
        $fusionGroupe = ($fusionGroupeIndex !== false) ? trim($row[$fusionGroupeIndex] ?? '') : '';
        if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;
        
        $formateurP = getFormattedName($row[$nomFormateurIndex] ?? null);
        $formateurS = getFormattedName($row[$nomFormateurSyncIndex] ?? null);
        $module = trim($row[$moduleIndex] ?? '');

        // --- Extraction des nouvelles données ---
        $colS_value = trim($row[$estRegionalIndex] ?? '');
        $est_regional = (strtoupper($colS_value) === 'O');
        $s1_heures = isset($row[$s1HeuresIndex]) ? floatval(str_replace(',', '.', $row[$s1HeuresIndex])) : 0;
        $s2_heures = isset($row[$s2HeuresIndex]) ? floatval(str_replace(',', '.', $row[$s2HeuresIndex])) : 0;
        
        // On ajoute les nouvelles données dans le tableau d'affectations
        if ($formateurP && $groupe && $module) {
            $affectations[] = [
                'formateur' => $formateurP, 'groupe' => $groupe, 'module' => $module, 'type' => 'presentiel',
                's1_heures' => $s1_heures, 's2_heures' => $s2_heures, 'est_regional' => $est_regional
            ];
        }
        if ($formateurS && $fusionGroupe && $module) {
            $affectations[] = [
                'formateur' => $formateurS, 'groupe' => $fusionGroupe, 'module' => $module, 'type' => 'synchrone',
                's1_heures' => $s1_heures, 's2_heures' => $s2_heures, 'est_regional' => $est_regional
            ];
        }
    }
    
    $groupesList = array_values(array_unique($groupes)); sort($groupesList);
    $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);
    
    $pdo->beginTransaction();

    // --- MODIFICATION MAJEURE : On ne supprime que les données volatiles ---
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee IN ('groupe', 'fusion_groupe', 'affectation')")
        ->execute([$etablissement_id]);
    
    // On insère les nouvelles données volatiles
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    if(!empty($groupesList)) $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    if(!empty($fusionGroupesList)) $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
    if(!empty($affectations)) $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);
    
    // On met à jour les données d'avancement (inchangé)
    $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)");
    $avancementStmt->execute([$etablissement_id, $fileName, json_encode($dataRows)]);

    // ON NE TOUCHE PAS à 'formateurs_details', 'calendrier', ni à la ligne 'formateur' de 'donnees_de_base'.

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Données importées avec succès.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>