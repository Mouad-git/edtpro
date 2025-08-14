<?php
/**
 * API pour Finaliser la Configuration Initiale (Version Avancée)
 *
 * Ce script sauvegarde toutes les données de configuration, en distinguant
 * les groupes Résidentiel et Alterné.
 */

// On inclut le gardien (qui vérifie 'utilisateur_id' et fournit '$etablissement_id')
require_once '../auth/session_check_setup.php';
// La connexion BDD est déjà faite par le gardien.

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$utilisateur_id = $_SESSION['utilisateur_id'];
// La variable $etablissement_id est maintenant disponible grâce à session_check_setup.php

// Validation des données reçues
if (empty($data['excelData']) || empty($data['formateursData']) || empty($data['espaces'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Des données de configuration essentielles sont manquantes.']);
    exit;
}

$rawExcelDataWithHeader = $data['excelData'];
$formateursData = $data['formateursData'];
$holidays = $data['holidays'] ?? [];
$vacations = $data['vacations'] ?? [];
$espaces = $data['espaces'];

if (empty($rawExcelDataWithHeader) || !isset($rawExcelDataWithHeader[0])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Les données Excel reçues sont vides ou mal formatées.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // --- 1. Sauvegarde des données des formateurs (inchangé) ---
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'formateur'")->execute([$etablissement_id]);
    $stmtFormateurs = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'formateur', ?)");
    $stmtFormateurs->execute([$etablissement_id, json_encode($formateursData)]);
    
    // --- 2. Extraction du reste des données, y compris le mode des groupes ---
    $fusionGroupes = []; 
    $affectations = [];
    
    $header = array_map('trim', $rawExcelDataWithHeader[0]);
    $dataRows = array_slice($rawExcelDataWithHeader, 1);
    
    // Définition des index de colonnes
    $groupeIndex = 8;        // Colonne I
    $modeIndex = 15;         // Colonne P
    $fusionGroupeIndex = 12; // Colonne M
    $moduleIndex = 16;       // Colonne Q
    $nomFormateurIndex = 20; // Colonne U
    $nomFormateurSyncIndex = 22; // Colonne W

    // On utilise un tableau associatif pour éviter les doublons de groupes
    $groupesMap = [];

    foreach ($dataRows as $row) {
        $groupe = trim($row[$groupeIndex] ?? '');
        $mode = trim($row[$modeIndex] ?? 'Résidentiel'); // Par défaut 'Résidentiel' si vide
        
        if ($groupe && !isset($groupesMap[$groupe])) {
            // On s'assure que le mode est soit 'Alterné', soit 'Résidentiel'
            $modeNormalise = (stripos($mode, 'Alterné') !== false) ? 'Alterné' : 'Résidentiel';
            $groupesMap[$groupe] = ['nom' => $groupe, 'mode' => $modeNormalise];
        }
        
        $fusionGroupe = trim($row[$fusionGroupeIndex] ?? '');
        if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;
        
        $formateurP = getFormattedName($row[$nomFormateurIndex] ?? null);
        $formateurS = getFormattedName($row[$nomFormateurSyncIndex] ?? null);
        $module = trim($row[$moduleIndex] ?? '');

        if ($formateurP && $groupe && $module) $affectations[] = ['formateur' => $formateurP, 'groupe' => $groupe, 'module' => $module, 'type' => 'presentiel'];
        if ($formateurS && $fusionGroupe && $module) $affectations[] = ['formateur' => $formateurS, 'groupe' => $fusionGroupe, 'module' => $module, 'type' => 'synchrone'];
    }
    
    // On transforme la map en une simple liste d'objets
    $groupesList = array_values($groupesMap);
    $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);
    
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    
    // On supprime les anciennes données avant d'insérer les nouvelles
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee IN ('groupe', 'fusion_groupe', 'affectation')")->execute([$etablissement_id]);

    // On sauvegarde la nouvelle structure de données pour les groupes
    if(!empty($groupesList)) $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    if(!empty($fusionGroupesList)) $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
    if(!empty($affectations)) $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);

    // --- 3, 4, 5, 6 : Les autres sauvegardes restent identiques ---
    // (Sauvegarde des données d'avancement, calendrier, espaces, et mise à jour de is_setup_complete)
    // ...

    // On met à jour la session pour la rendre "complète"
    $_SESSION['etablissement_id'] = $etablissement_id;
    
    $pdo->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

// La fonction getFormattedName doit être définie pour être utilisée.
function getFormattedName($name) {
    if (!$name || !is_string($name)) return '';
    $words = array_filter(preg_split('/\s+/', trim(strtoupper($name))));
    $wordCount = count($words);
    if ($wordCount === 0) return '';
    if ($wordCount <= 1) return $words[0];
    if ($wordCount >= 3 && mb_strlen($words[1]) <= 3) return $words[1] . ' ' . $words[2];
    $dernierMot = $words[$wordCount - 1];
    if (mb_strlen($dernierMot) <= 2 && $wordCount > 1) return $words[$wordCount - 2] . ' ' . $dernierMot;
    return $dernierMot;
}
?>