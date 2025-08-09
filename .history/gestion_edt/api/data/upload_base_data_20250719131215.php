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
    $sheet = $spreadsheet->getSheetByName('AvancementProgramme');
        if (!$sheet) {
            throw new Exception("La feuille 'AvancementProgramme' est introuvable.");
        }
        $rows = $sheet->toArray(null, true, true, true);

        // --- Logique d'extraction (similaire à votre code précédent) ---
        if (isset($rows[1]['A']) && $rows[1]['A'] === "Date MAJ") array_shift($rows);
        
        $formateurs = []; $groupes = []; $fusionGroupes = []; $affectations = [];

// Votre fonction getFormattedName reste la même
function getFormattedName($name) {
    if (!$name || !is_string($name)) return '';
    $words = preg_split('/\s+/', trim($name));
    if (count($words) <= 1) return $name;
    if (count($words) === 2) return $words[1];
    $avantDernier = $words[count($words) - 2];
    $dernier = $words[count($words) - 1];
    return strlen($avantDernier) < 4 ? "$avantDernier $dernier" : $dernier;
}

// On saute la première ligne (en-têtes)
array_shift($rows); 

foreach ($rows as $row) {
    // On vérifie si la ligne n'est pas complètement vide
    if (empty(array_filter($row))) {
        continue;
    }

    // On accède aux colonnes par leur indice numérique
    // U=20, W=22, I=8, M=12, Q=16
    $formateurP   = getFormattedName(isset($row[20]) ? $row[20] : null);
    $formateurS   = getFormattedName(isset($row[22]) ? $row[22] : null);
    $groupe       = trim(isset($row[8]) ? $row[8] : '');
    $fusionGroupe = trim(isset($row[12]) ? $row[12] : '');
    $module       = trim(isset($row[16]) ? $row[16] : '');

    if ($formateurP) $formateurs[] = $formateurP;
    if ($formateurS) $formateurs[] = $formateurS;
    if ($groupe) $groupes[] = $groupe;
    if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;

    if ($formateurP && $groupe && $module) $affectations[] = ['formateur' => $formateurP, 'groupe' => $groupe, 'module' => $module, 'type' => 'presentiel'];
    if ($formateurS && $fusionGroupe && $module) $affectations[] = ['formateur' => $formateurS, 'groupe' => $fusionGroupe, 'module' => $module, 'type' => 'synchrone'];
}
        

        $formateursList = array_values(array_unique($formateurs)); sort($formateursList);
        $groupesList = array_values(array_unique($groupes)); sort($groupesList);
        $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);

        header('Content-Type: application/json'); // Pour que la réponse reste lisible
echo json_encode([
    'debug_message' => 'Contenu extrait du fichier Excel',
    'formateurs_trouves' => $formateursList,
    'groupes_trouves' => $groupesList,
    'affectations_trouvees' => count($affectations)
]);
exit;
        
        // --- Transaction de base de données ---
        $pdo->beginTransaction();

        // On vide les anciennes données pour cet établissement
        $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ?")->execute([$etablissement_id]);

        // On insère les nouvelles
        $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
        $insertStmt->execute([$etablissement_id, 'formateur', json_encode($formateursList)]);
        $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
        $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
        $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);

        // On met à jour la table d'avancement
        $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)");
        $avancementStmt->execute([$etablissement_id, $fileName, json_encode($rows)]);

        $pdo->commit();

        echo json_encode(['success' => true]);
    echo json_encode([
        'success' => true, 
        'message' => 'Fichier traité et données de base mises à jour avec succès.'
    ]);

} catch (\Exception $e) catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    
    // ▼▼▼ MODIFICATION IMPORTANTE POUR LE DÉBOGAGE ▼▼▼
    echo json_encode([
        'success' => false,
        'message' => "Erreur PHP: " . $e->getMessage(), // Le message d'erreur précis
        'file'    => $e->getFile(),                   // Le fichier où l'erreur s'est produite
        'line'    => $e->getLine()                    // La ligne exacte
    ]);
    // ▲▲▲ FIN DE LA MODIFICATION ▲▲▲
}

?>
