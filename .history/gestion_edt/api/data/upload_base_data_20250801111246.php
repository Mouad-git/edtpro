<?php
/**
 * API pour la Mise à Jour des Données de Base via Fichier Excel
 *
 * Ce script est appelé depuis la page admin.html lorsqu'un directeur
 * importe un nouveau fichier "AvancementProgramme".
 *
 * Logique Clé :
 * 1. Il lit le fichier Excel pour extraire les données "volatiles" :
 *    - La liste des groupes et des groupes de fusion.
 *    - La liste des affectations (quel formateur pour quel module/groupe).
 * 2. Il met à jour la table 'donnees_de_base' en remplaçant UNIQUEMENT
 *    les entrées de type 'groupe', 'fusion_groupe', et 'affectation'.
 * 3. Il met à jour la table 'donnees_avancement' avec le contenu brut du nouveau fichier.
 * 4. IL NE TOUCHE PAS aux tables 'formateurs_details', 'calendrier', ou 'espaces'.
 */

// On inclut le gardien strict (l'utilisateur doit être pleinement authentifié)
require_once '../auth/session_check.php';
// On charge les dépendances (BDD et PhpSpreadsheet)
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

// Récupération des ID de session
$etablissement_id = $_SESSION['etablissement_id'];

// Vérification de l'upload du fichier
if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Fichier manquant ou erreur d\'upload.']);
    exit;
}

$filePath = $_FILES['excelFile']['tmp_name'];
$fileName = basename($_FILES['excelFile']['name']);

try {
    // --- LECTURE DU FICHIER EXCEL ---
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheetByName('AvancementProgramme');
    if (!$sheet) {
        throw new Exception("Feuille 'AvancementProgramme' introuvable.");
    }
    
    $allData = $sheet->toArray(null, true, false, false);
    $cleanedRows = [];
    foreach ($allData as $row) {
        if (!empty(array_filter($row))) {
            $cleanedRows[] = $row;
        }
    }

    if (count($cleanedRows) < 2) {
        throw new Exception("Le fichier Excel semble vide ou ne contient pas de données valides.");
    }

    $header = array_map('trim', array_shift($cleanedRows));
    $dataRows = $cleanedRows;

    // --- EXTRACTION DES DONNÉES VOLATILES ---
    $groupes = []; $fusionGroupes = []; $affectations = [];
    
    $nomFormateurIndex = array_search('Formateur Affecté Présentiel Actif', $header);
    $nomFormateurSyncIndex = array_search('Formateur Affecté Syn Actif', $header);
    $groupeIndex = array_search('Groupe', $header);
    $fusionGroupeIndex = array_search('FusionGroupe', $header);
    $moduleIndex = array_search('Code Module', $header);

    if ($groupeIndex === false || $moduleIndex === false) {
        throw new Exception("Les colonnes 'Groupe' ou 'Code Module' sont introuvables dans l'en-tête du fichier Excel.");
    }
    
    // On réutilise la même fonction de formatage de nom
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
        
        $formateurP = ($nomFormateurIndex !== false) ? getFormattedName($row[$nomFormateurIndex] ?? null) : '';
        $formateurS = ($nomFormateurSyncIndex !== false) ? getFormattedName($row[$nomFormateurSyncIndex] ?? null) : '';
        $module = trim($row[$moduleIndex] ?? '');

        if ($formateurP && $groupe && $module) $affectations[] = ['formateur' => $formateurP, 'groupe' => $groupe, 'module' => $module, 'type' => 'presentiel'];
        if ($formateurS && $fusionGroupe && $module) $affectations[] = ['formateur' => $formateurS, 'groupe' => $fusionGroupe, 'module' => $module, 'type' => 'synchrone'];
    }

    $groupesList = array_values(array_unique($groupes)); sort($groupesList);
    $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);

    // --- MISE À JOUR DE LA BASE DE DONNÉES ---
    $pdo->beginTransaction();

    // 1. On supprime UNIQUEMENT les anciennes données volatiles
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee IN ('groupe', 'fusion_groupe', 'affectation')")
        ->execute([$etablissement_id]);
    
    // 2. On insère les nouvelles données volatiles
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    if(!empty($groupesList)) $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    if(!empty($fusionGroupesList)) $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
    if(!empty($affectations)) $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);
    
    // 3. On met à jour la table 'donnees_avancement' avec le contenu brut du nouveau fichier
    $avancementStmt = $pdo->prepare(
        "INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)"
    );
    $avancementStmt->execute([$etablissement_id, $fileName, json_encode($dataRows)]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Erreur lors du traitement du fichier: " . $e->getMessage()]);
}
?>