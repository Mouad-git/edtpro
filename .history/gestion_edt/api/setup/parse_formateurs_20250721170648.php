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
    if (!$sheet) throw new Exception("Feuille 'AvancementProgramme' introuvable.");
    
    $allRows = $sheet->toArray(null, true, false, true);
    $header = array_map('trim', array_shift($allRows)); 
    
    $nomFormateurIndex = array_search('Formateur Affecté Présentiel Actif', $header);
    $matriculeIndex = array_search('Mle Affecté Présentiel Actif', $header);
    
    if ($nomFormateurIndex === false) throw new Exception("La colonne 'Formateur Affecté Présentiel Actif' est introuvable.");
    if ($matriculeIndex === false) throw new Exception("La colonne 'Matricule Formateur Présentiel' est introuvable.");

    $formateursData = [];
    $processedNames = [];

    // --- LA FONCTION A ÉTÉ MISE À JOUR ICI ---
    function getFormattedName($name) {
        if (!$name || !is_string($name)) return '';
        $words = array_filter(preg_split('/\s+/', trim(strtoupper($name))));
        $wordCount = count($words);

        if ($wordCount === 0) return '';
        if ($wordCount <= 1) return $words[0];

        if ($wordCount >= 3 && mb_strlen($words[1]) <= 3) {
            return $words[1] . ' ' . $words[2];
        }
        
        $dernierMot = $words[$wordCount - 1];
        if (mb_strlen($dernierMot) <= 2 && $wordCount > 1) {
            return $words[$wordCount - 2] . ' ' . $dernierMot;
        }

        return $dernierMot;
    }

    foreach ($allRows as $row) {
        $formateurNomRaw = $row[$nomFormateurIndex] ?? null;
        $matricule = trim($row[$matriculeIndex] ?? '');
        
        $formateurNomFormatte = getFormattedName($formateurNomRaw);

        if ($formateurNomFormatte && !in_array($formateurNomFormatte, $processedNames)) {
            $processedNames[] = $formateurNomFormatte;

            $isVacataire = !ctype_digit($matricule);
            $masseHoraireParDefaut = $isVacataire ? 0 : 910;
            $emailGenere = '';

            if (!$isVacataire) {
                $emailName = strtolower($formateurNomRaw);
                if (strpos($emailName, 'el ') === 0) $emailName = substr($emailName, 3);
                $email_prefix = preg_replace('/\s+/', '.', $emailName);
                $email_prefix = preg_replace('/[^a-z0-9\.]/', '', $email_prefix);
                $emailGenere = $email_prefix . '@ofppt.ma';
            }

            $formateursData[] = [
                'nom' => $formateurNomFormatte,
                'email' => $emailGenere,
                'matricule' => $matricule,
                'masse_horaire' => $masseHoraireParDefaut
            ];
        }
    }

    if (empty($formateursData)) {
        throw new Exception("Aucun formateur valide n'a été trouvé. Vérifiez la colonne 'Formateur Affecté Présentiel Actif'.");
    }
    
    usort($formateursData, fn($a, $b) => strcmp($a['nom'], $b['nom']));

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