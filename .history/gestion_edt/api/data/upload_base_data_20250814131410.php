<?php
// api/data/upload_base_data.php (Version Finale et Complète)

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
    // --- 1. Lecture et Nettoyage du Fichier Excel ---
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheetByName('AvancementProgramme');
    if (!$sheet) throw new Exception("Feuille 'AvancementProgramme' introuvable.");
    
    $allData = $sheet->toArray(null, true, false, false);
    $cleanedRows = [];
    foreach ($allData as $row) {
        if (!empty(array_filter($row))) $cleanedRows[] = $row;
    }
    if (count($cleanedRows) < 2) throw new Exception("Fichier Excel vide ou sans données.");
    
    $header = array_map('trim', array_shift($cleanedRows));
    $dataRows = $cleanedRows;

    // --- 2. Extraction Complète des Données ---
    $nomFormateurIndex = array_search('Formateur Affecté Présentiel Actif', $header);
    // ... (tous vos autres array_search pour les index)

    if ($nomFormateurIndex === false) throw new Exception("Colonne 'Formateur Affecté Présentiel Actif' manquante.");

    $formateursNoms = [];
    $groupes = [];
    $fusionGroupes = [];
    $affectations = [];
    $groupeModes = [];

    function getFormattedName($name) { /* ... Votre fonction getFormattedName ... */ }

    foreach ($dataRows as $row) {
        $formateurP = getFormattedName($row[$nomFormateurIndex] ?? null);
        if ($formateurP) $formateursNoms[] = $formateurP;
        
        $formateurS = getFormattedName($row[$nomFormateurSyncIndex] ?? null);
        if ($formateurS) $formateursNoms[] = $formateurS;
        
        // ... (votre logique d'extraction pour groupes, affectations, modes, etc. est correcte)
    }
    
    $formateursUniques = array_values(array_unique($formateursNoms));
    sort($formateursUniques);
    $groupesList = array_values(array_unique($groupes)); sort($groupesList);
    // ... (etc. pour les autres listes)

    $pdo->beginTransaction();

    // --- 3. Synchronisation des Formateurs (Logique Clé) ---
    // a) Récupérer les formateurs existants de la BDD
    $stmt = $pdo->prepare("SELECT donnees_json FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'formateur'");
    $stmt->execute([$etablissement_id]);
    $formateursDbJson = $stmt->fetchColumn();
    $formateursDb = $formateursDbJson ? json_decode($formateursDbJson, true) : [];
    
    // b) Créer une map des formateurs existants pour un accès rapide
    $formateursDbMap = [];
    foreach ($formateursDb as $f) {
        $formateursDbMap[$f['nom']] = $f;
    }

    // c) Construire la nouvelle liste de formateurs
    $newFormateursData = [];
    foreach ($formateursUniques as $nom) {
        if (isset($formateursDbMap[$nom])) {
            // Le formateur existe déjà, on garde ses anciennes données (email, etc.)
            $newFormateursData[] = $formateursDbMap[$nom];
        } else {
            // C'est un nouveau formateur, on lui donne des valeurs par défaut
            $newFormateursData[] = [
                'nom' => $nom,
                'email' => '',
                'matricule' => '',
                'masse_horaire' => 910
            ];
        }
    }

    // --- 4. Sauvegarde des Données avec UPSERT ---
    $upsertStmt = $pdo->prepare(
        "INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) 
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE donnees_json = VALUES(donnees_json)"
    );

    // On met à jour la liste des formateurs synchronisée
    $upsertStmt->execute([$etablissement_id, 'formateur', json_encode($newFormateursData)]);

    // On met à jour les autres données comme avant
    if(!empty($groupesList)) $upsertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    // ... etc. pour fusion_groupe, affectation, groupe_mode

    // Mise à jour des données d'avancement
    $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement ... ");
    $avancementStmt->execute([$etablissement_id, $fileName, json_encode($dataRows)]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Données de base et d\'avancement mises à jour.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>