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
    $matriculeIndex = array_search('', $header);
    
    if ($nomFormateurIndex === false) throw new Exception("La colonne 'Formateur Affecté Présentiel Actif' est introuvable.");
    if ($matriculeIndex === false) throw new Exception("La colonne 'Matricule Formateur Présentiel' est introuvable.");

    $formateursData = [];
    $processedNames = [];

    function getFormattedName($name) {
        if (!$name || !is_string($name)) return '';
        $name = trim($name);
        return ucwords(strtolower($name));
    }

    foreach ($allRows as $row) {
        $formateurNomRaw = $row[$nomFormateurIndex] ?? null;
        $matricule = trim($row[$matriculeIndex] ?? '');
        
        $formateurNom = getFormattedName($formateurNomRaw);

        if ($formateurNom && !in_array($formateurNom, $processedNames)) {
            $processedNames[] = $formateurNom;

            // --- NOUVELLE LOGIQUE POUR VACATAIRES vs PERMANENTS ---
            
            $isVacataire = !ctype_digit($matricule); // VRAI si le matricule n'est PAS composé uniquement de chiffres
            
            $masseHoraireParDefaut = $isVacataire ? 0 : 910;
            $emailGenere = '';

            if (!$isVacataire) {
                // Ce n'est PAS un vacataire, on génère l'email professionnel
                $emailName = strtolower($formateurNomRaw);
                if (strpos($emailName, 'el ') === 0) {
                    $emailName = substr($emailName, 3);
                }
                $email_prefix = preg_replace('/\s+/', '.', $emailName);
                $email_prefix = preg_replace('/[^a-z0-9\.]/', '', $email_prefix);
                $emailGenere = $email_prefix . '@ofppt.ma';
            }
            // Si c'est un vacataire, $emailGenere reste une chaîne vide.

            $formateursData[] = [
                'nom' => $formateurNom,
                'email' => $emailGenere, // Vide pour les vacataires, généré pour les autres
                'matricule' => $matricule, // On garde toujours le matricule
                'masse_horaire' => $masseHoraireParDefaut // 0 pour les vacataires, 910 pour les autres
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