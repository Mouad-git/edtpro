<?php
// api/data/upload_base_data.php

// Augmente les limites pour ce script spécifiquement (alternative à php.ini)
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300);

header('Content-Type: application/json');

// --- GESTION ROBUSTE DE L'UPLOAD ---

// 1. Vérifier si un fichier a été envoyé et s'il n'y a pas d'erreur
if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE   => 'Le fichier dépasse la taille autorisée par le serveur (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'Le fichier dépasse la taille autorisée par le formulaire.',
        UPLOAD_ERR_PARTIAL    => 'Le fichier n\'a été que partiellement téléchargé.',
        UPLOAD_ERR_NO_FILE    => 'Aucun fichier n\'a été téléchargé.',
        UPLOAD_ERR_NO_TMP_DIR => 'Erreur serveur : dossier temporaire manquant.',
        UPLOAD_ERR_CANT_WRITE => 'Erreur serveur : impossible d\'écrire le fichier sur le disque.',
        UPLOAD_ERR_EXTENSION  => 'Une extension PHP a interrompu l\'envoi.',
    ];
    $errorCode = $_FILES['excelFile']['error'] ?? UPLOAD_ERR_NO_FILE;
    $message = $uploadErrors[$errorCode] ?? 'Erreur inconnue lors du téléchargement.';
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

$tmpFilePath = $_FILES['excelFile']['tmp_name'];

// --- TRAITEMENT DU FICHIER ---

require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

try {
    // Charger le fichier avec PhpSpreadsheet
    $spreadsheet = IOFactory::load($tmpFilePath);
    
    // ... Votre logique existante pour extraire les données du fichier Excel ...
    // $sheet = $spreadsheet->getActiveSheet();
    // $data = $sheet->toArray();
    // ... etc.

    // Par exemple, sauvegarder les données extraites
    // file_put_contents('chemin/vers/donnees.json', json_encode($donnees_extraites));

    // Envoyer une réponse de succès
    echo json_encode([
        'success' => true, 
        'message' => 'Fichier traité et données de base mises à jour avec succès.'
    ]);

} catch (\Exception $e) {
    // Capturer toute autre erreur (ex: fichier corrompu, problème de lecture)
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur lors du traitement du fichier Excel : ' . $e->getMessage()
    ]);
}

?>
