<?php
// api/setup/parse_formateurs.php

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
    
    $allRows = $sheet->toArray(null, true, false, true);
    
    $header = array_map('trim', array_shift($allRows)); 
    
    // --- CORRECTION APPLIQUÉE ICI ---
    $nomFormateurIndex = array_search('Formateur Affecté Présentiel Actif', $header);
    $matriculeIndex = array_search('Mle Affecté Présentiel Actif', $header); // Assurez-vous que ce titre est bon
    
    if ($nomFormateurIndex === false) {
        throw new Exception("La colonne 'Formateur Affecté Présentiel Actif' est introuvable dans le fichier Excel. Veuillez vérifier l'en-tête.");
    }
    if ($matriculeIndex === false) {
        throw new Exception("La colonne 'Matricule Formateur Présentiel' est introuvable. Veuillez vérifier l'en-tête.");
    }
    // --- FIN DE LA CORRECTION ---

    $formateursData = [];
    $processedNames = [];

    function getFormattedName($name) {
        if (!$name || !is_string($name)) return '';
        $name = trim($name);
        $words = array_filter(preg_split('/\s+/', $name));
        if (count($words) === 0) return '';
        if (count($words) <= 1) return $name;
        if (count($words) === 2) return $words[1];
        $avantDernier = $words[count($words) - 2];
        $dernier = $words[count($words) - 1];
        return mb_strlen($avantDernier) < 4 ? "$avantDernier $dernier" : $dernier;
    }

    foreach ($allRows as $row) {
        $formateurNomRaw = $row[$nomFormateurIndex] ?? null;
        $matricule = trim($row[$matriculeIndex] ?? '');
        
        $formateurNom = getFormattedName($formateurNomRaw);

        if ($formateurNom && !in_array($formateurNom, $processedNames)) {
            $processedNames[] = $formateurNom;

            $nameParts = preg_split('/\s+/', strtolower($formateurNomRaw));
            $email_prefix = str_replace(['-', "'"], '', implode('.', $nameParts));
            
            $matricule_final = (ctype_digit($matricule)) ? $matricule : '';

            $formateursData[] = [
                'nom' => $formateurNom,
                'email_prefix' => $email_prefix,
                'matricule' => $matricule_final
            ];
        }
    }

    if (empty($formateursData)) {
        throw new Exception("Aucun formateur valide n'a été trouvé dans le fichier. Assurez-vous que la colonne 'Formateur Affecté Présentiel Actif' est bien remplie.");
    }
    
    sort($processedNames); // On trie les noms pour un affichage alphabétique
    $sortedFormateursData = [];
    foreach($processedNames as $name) {
        foreach($formateursData as $data) {
            if ($data['nom'] === $name) {
                $sortedFormateursData[] = $data;
                break;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'formateurs' => $sortedFormateursData,
        'rawExcelData' => $allRows
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>