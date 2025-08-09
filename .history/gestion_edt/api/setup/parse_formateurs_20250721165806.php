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
    $matriculeIndex = array_search('Matricule Formateur Présentiel', $header);
    
    if ($nomFormateurIndex === false) throw new Exception("La colonne 'Formateur Affecté Présentiel Actif' est introuvable.");
    if ($matriculeIndex === false) throw new Exception("La colonne 'Matricule Formateur Présentiel' est introuvable.");

    $formateursData = [];
    $processedNames = [];

    // --- LA FONCTION A ÉTÉ MISE À JOUR ICI ---
    function getFormattedName($name) {
        if (!$name || !is_string($name)) return '';
        $words = array_filter(preg_split('/\s+/', trim(strtoupper($name)))); // On met tout en majuscules pour être cohérent
        $wordCount = count($words);

        if ($wordCount === 0) return '';
        if ($wordCount <= 1) return $words[0];
        
        $dernierMot = $words[$wordCount - 1];
        if (mb_strlen($dernierMot) <= 2 && $wordCount > 1) {
            return $words[$wordCount - 2] . ' ' . $dernierMot;
        }

        return $dernierMot;
    }

    foreach ($allRows as $row) {
        $formateurNomRaw = $row[$nomFormateurIndex] ?? null;
        $matricule = trim($row[$matriculeIndex] ?? '');
        
        // On utilise la nouvelle fonction de formatage
        $formateurNomFormatte = getFormattedName($formateurNomRaw);

        // On vérifie les doublons sur le nom formaté
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
                'nom' => $formateurNomFormatte, // On stocke le nom formaté
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
?>```

### Explication de la Correction

1.  **Remplacement de `getFormattedName` :** J'ai remplacé votre fonction qui mettait simplement les premières lettres en majuscules par la logique plus complexe que vous utilisiez déjà. Elle prend maintenant le dernier mot du nom, sauf si celui-ci est très court.
2.  **Mise en Majuscules :** J'ai ajouté `strtoupper()` dans la fonction pour que tous les noms de famille soient uniformément en majuscules (ex: "Dupont" -> "DUPONT"), ce qui est plus standard pour ce type d'affichage.
3.  **Vérification des Doublons :** Le script vérifie maintenant les doublons en se basant sur le nom **formaté**, ce qui est plus correct.
4.  **Stockage :** Le tableau `$formateursData` stocke maintenant le nom déjà formaté (`'nom' => $formateurNomFormatte`).

Aucune modification n'est nécessaire dans votre fichier `setup.html`. Il se contentera d'afficher le nom qu'il reçoit du backend, qui sera maintenant le nom de famille.

**Action Immédiate :**
Remplacez simplement le contenu de votre fichier `api/setup/parse_formateurs.php` par le code ci-dessus et réessayez. La liste des formateurs à l'étape 2 devrait maintenant afficher les noms de famille.