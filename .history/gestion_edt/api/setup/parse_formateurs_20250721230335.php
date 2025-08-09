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
    
    $dataWithHeader = $sheet->toArray(null, true, false, true);

// On prend la première ligne comme en-tête
$header = array_map('trim', $dataWithHeader[0]);
// On prend le reste comme les lignes de données
$allRows = array_slice($dataWithHeader, 1);
    
    $nomFormateurIndex = array_search('Formateur Affecté Présentiel Actif', $header);
    $matriculeIndex = array_search('Mle Affecté Présentiel Actif', $header);
    
    if ($nomFormateurIndex === false) throw new Exception("La colonne 'Formateur Affecté Présentiel Actif' est introuvable.");
    if ($matriculeIndex === false) throw new Exception("La colonne 'Matricule Formateur Présentiel' est introuvable.");

    $formateursData = [];
    $processedNames = [];

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
                // --- NOUVELLE LOGIQUE D'EMAIL ---
                $nomCompletMinuscules = strtolower($formateurNomRaw);
                $parts = preg_split('/\s+/', $nomCompletMinuscules);
                $prenom = $parts[0] ?? '';
                $nomDeFamilleSansEspace = str_replace(' ', '', strtolower($formateurNomFormatte));
                $emailGenere = $prenom . '.' . $nomDeFamilleSansEspace . '@ofppt.ma';
                $emailGenere = iconv('UTF-8', 'ASCII//TRANSLIT', $emailGenere);
                $emailGenere = preg_replace('/[^a-z0-9\.@]/', '', $emailGenere);
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