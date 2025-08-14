<?php
// api/data/upload_base_data.php (Version Corrigée et Simplifiée)

// On utilise le gardien strict, car cette page n'est accessible qu'après configuration
require_once '../auth/session_check.php';
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

$etablissement_id = $_SESSION['etablissement_id'];

if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aucun fichier reçu ou erreur d\'upload.']);
    exit;
}

$filePath = $_FILES['excelFile']['tmp_name'];
$fileName = basename($_FILES['excelFile']['name']);

try {
    // --- ÉTAPE 1 : LIRE ET VALIDER LE FICHIER EXCEL ---
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheetByName('AvancementProgramme');
    if (!$sheet) {
        throw new Exception("La feuille 'AvancementProgramme' est introuvable.");
    }
    
    // On récupère toutes les lignes de données (sans l'en-tête)
    $allRows = $sheet->toArray(null, true, false, true);
    if (count($allRows) > 1) {
        $dataRows = array_slice($allRows, 1);
    } else {
        $dataRows = [];
    }
    
    if (empty($dataRows)) {
        throw new Exception("Le fichier Excel ne contient aucune ligne de données.");
    }

    // --- ÉTAPE 2 : METTRE À JOUR UNIQUEMENT LA TABLE `donnees_avancement` ---
    // On ne touche PLUS à la table `donnees_de_base`.
    
    $stmt = $pdo->prepare(
        "INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) 
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)"
    );
    
    $success = $stmt->execute([
        $etablissement_id, 
        $fileName, 
        json_encode($dataRows) // On sauvegarde les lignes de données
    ]);

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Les données d\'avancement ont été mises à jour.']);
    } else {
        throw new Exception("La mise à jour des données d'avancement a échoué.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>