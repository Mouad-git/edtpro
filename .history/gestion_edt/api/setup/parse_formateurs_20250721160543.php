<?php
// api/setup/parse_formateurs.php
require_once '../../vendor/autoload.php';
require_once '../auth/session_check_setup.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json');

if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Fichier manquant.']);
    exit;
}

try {
    $spreadsheet = IOFactory::load($_FILES['excelFile']['tmp_name']);
    $sheet = $spreadsheet->getSheetByName('AvancementProgramme');
    if (!$sheet) throw new Exception("Feuille 'AvancementProgramme' introuvable.");

    $allRows = $sheet->toArray(null, true, true, true);
    $header = array_shift($allRows);

    $formateursData = [];
    $processedNames = [];

    // Votre fonction de formatage de nom
    function getFormattedName($name) { /* ... */ }

    foreach ($allRows as $row) {
        $formateurNom = getFormattedName($row['U'] ?? null);
        $matricule = trim($row['T'] ?? '');

        if ($formateurNom && !in_array($formateurNom, $processedNames)) {
            $processedNames[] = $formateurNom; // Pour éviter les doublons

            // Logique de génération de l'email
            $nameParts = preg_split('/\s+/', strtolower($formateurNom));
            $firstName = $nameParts[0];
            $lastName = implode('', array_slice($nameParts, 1));
            $email_prefix = $firstName . '.' . $lastName;

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
        throw new Exception("Aucun formateur trouvé dans la colonne U du fichier.");
    }
    
    // On renvoie les formateurs et les données brutes
    echo json_encode([
        'success' => true,
        'formateurs' => $formateursData,
        'rawExcelData' => $allRows // On renvoie toutes les lignes pour l'étape finale
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>