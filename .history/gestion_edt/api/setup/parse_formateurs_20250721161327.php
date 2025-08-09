<?php
// api/setup/parse_formateurs.php

// Les require_once restent les mêmes
require_once '../../vendor/autoload.php';
require_once '../auth/session_check.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Fichier manquant ou erreur d\'upload.']);
    exit;
}

try {
    $spreadsheet = IOFactory::load($_FILES['excelFile']['tmp_name']);
    $sheet = $spreadsheet->getSheetByName('AvancementProgramme');
    if (!$sheet) {
        throw new Exception("Feuille 'AvancementProgramme' introuvable. Veuillez vérifier le nom de la feuille.");
    }
    
    // On récupère toutes les données sous forme de tableau numérique
    $allRows = $sheet->toArray(null, true, false, true);
    
    // --- AMÉLIORATION CRUCIALE : Détection des index des colonnes ---
    $header = array_map('trim', array_shift($allRows)); // On prend la première ligne comme en-tête
    
    // On cherche l'index (le numéro) des colonnes qui nous intéressent
    $nomFormateurIndex = array_search('Nom & Prénom Présentiel', $header); // La colonne U
    $matriculeIndex = array_search('Matricule Formateur Présentiel', $header); // La colonne T
    
    // On vérifie si les colonnes ont été trouvées
    if ($nomFormateurIndex === false) {
        throw new Exception("La colonne 'Nom & Prénom Présentiel' est introuvable dans le fichier Excel. Veuillez vérifier l'en-tête.");
    }
     if ($matriculeIndex === false) {
        throw new Exception("La colonne 'Matricule Formateur Présentiel' est introuvable. Veuillez vérifier l'en-tête.");
    }

    $formateursData = [];
    $processedNames = [];

    // --- VOTRE FONCTION getFormattedName (légèrement améliorée) ---
    function getFormattedName($name) {
        if (!$name || !is_string($name)) return '';
        $name = trim($name);
        $words = array_filter(preg_split('/\s+/', $name));
        if (count($words) === 0) return '';
        if (count($words) <= 1) return $name;
        if (count($words) === 2) return $words[1]; // S'il y a 2 mots, on prend le deuxième. À ajuster si ce n'est pas le comportement voulu.
        $avantDernier = $words[count($words) - 2];
        $dernier = $words[count($words) - 1];
        return mb_strlen($avantDernier) < 4 ? "$avantDernier $dernier" : $dernier;
    }

    foreach ($allRows as $row) {
        // On accède aux données par leur numéro d'index, pas par la lettre
        $formateurNomRaw = $row[$nomFormateurIndex] ?? null;
        $matricule = trim($row[$matriculeIndex] ?? '');
        
        $formateurNom = getFormattedName($formateurNomRaw);

        if ($formateurNom && !in_array($formateurNom, $processedNames)) {
            $processedNames[] = $formateurNom;

            // Logique de génération de l'email
            $nameParts = preg_split('/\s+/', strtolower($formateurNomRaw));
            $email_prefix = str_replace('-', '', implode('.', $nameParts)); // Gère les noms composés
            
            // Logique du matricule
            $matricule_final = (ctype_digit($matricule)) ? $matricule : '';

            $formateursData[] = [
                'nom' => $formateurNom,
                'email_prefix' => $email_prefix,
                'matricule' => $matricule_final
            ];
        }
    }

    if (empty($formateursData)) {
        throw new Exception("Aucun formateur valide n'a été trouvé dans le fichier. Assurez-vous que la colonne 'Nom & Prénom Présentiel' est bien remplie.");
    }
    
    // On renvoie les formateurs triés et les données brutes
    sort($formateursData);
    echo json_encode([
        'success' => true,
        'formateurs' => $formateursData,
        'rawExcelData' => $allRows
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>