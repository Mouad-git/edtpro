<?php
/**
 * API pour la Mise à Jour des Données de Base depuis un Fichier Excel
 *
 * Ce script est appelé depuis la page principale (emploi.html).
 * Il met à jour les données volatiles (groupes, affectations, modes) et les données d'avancement.
 * Il utilise une logique UPSERT pour la ligne 'groupe_mode' afin d'éviter les doublons.
 */

// On utilise le gardien strict, car cette page n'est accessible qu'après configuration.
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
    // --- ÉTAPE 1 : LIRE ET VALIDER LE FICHIER EXCEL ---
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheetByName('AvancementProgramme');
    if (!$sheet) {
        throw new Exception("La feuille 'AvancementProgramme' est introuvable.");
    }
    
    // Nettoyage des lignes vides pour obtenir un tableau de données propre
    $allData = $sheet->toArray(null, true, false, false);
    $cleanedRows = [];
    foreach ($allData as $row) {
        if (!empty(array_filter($row))) {
            $cleanedRows[] = $row;
        }
    }
    if (count($cleanedRows) < 2) {
        throw new Exception("Le fichier Excel semble vide ou ne contient pas de données valides.");
    }
    
    $header = array_map('trim', array_shift($cleanedRows));
    $dataRows = $cleanedRows;

    // --- ÉTAPE 2 : EXTRACTION DES DONNÉES ---
    // Définition des index de colonnes par leur nom
    $groupeIndex = array_search('Groupe', $header);
    $fusionGroupeIndex = array_search('FusionGroupe', $header);
    $moduleIndex = array_search('Code Module', $header);
    $nomFormateurIndex = array_search('Formateur Affecté Présentiel Actif', $header);
    $nomFormateurSyncIndex = array_search('Formateur Affecté Syn Actif', $header);
    $modeIndex = array_search('Mode', $header);
    
    // Indices fixes pour les colonnes X et AB (nouvelle logique de semestre)
    $colonneXIndex = 23;         // Colonne X (premier semestre) -> s1_heures
    $colonneABIndex = 27;        // Colonne AB (deuxième semestre) -> s2_heures
    
    // Indice fixe pour la colonne S (régional)
    $colonneSIndex = 18;         // Colonne S (régional: O=true, N=false)

    // Validation des colonnes essentielles
    if ($groupeIndex === false || $moduleIndex === false || $nomFormateurIndex === false) {
        throw new Exception("Les colonnes 'Groupe', 'Code Module' ou 'Formateur Affecté Présentiel Actif' sont manquantes.");
    }

    $groupes = []; 
    $fusionGroupes = []; 
    $affectations = [];
    $groupeModes = [];
    $formateursListRaw = [];

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

    foreach ($dataRows as $row) {
        $groupe = trim($row[$groupeIndex] ?? '');
        if ($groupe) $groupes[] = $groupe;
        
        $fusionGroupe = ($fusionGroupeIndex !== false) ? trim($row[$fusionGroupeIndex] ?? '') : '';
        if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;

        if ($groupe && $modeIndex !== false) {
            $modeRaw = trim((string)($row[$modeIndex] ?? ''));
            if ($modeRaw !== '') {
                $groupeModes[$groupe] = (stripos($modeRaw, 'ALT') !== false) ? 'Alterné' : 'Résidentiel';
            }
        }
        
        $formateurP = getFormattedName($row[$nomFormateurIndex] ?? null);
        $formateurS = ($nomFormateurSyncIndex !== false) ? getFormattedName($row[$nomFormateurSyncIndex] ?? null) : '';
        $module = trim($row[$moduleIndex] ?? '');

        // Utilisation de la colonne S pour déterminer si le module est régional
        $est_regional = false;
        if (isset($row[$colonneSIndex])) {
            $valeurRegional = strtoupper(trim($row[$colonneSIndex]));
            if ($valeurRegional === 'O') {
                $est_regional = true;
            } elseif ($valeurRegional === 'N') {
                $est_regional = false;
            }
        }
        
        // Utilisation des colonnes X et AB pour remplir s1_heures et s2_heures
        $s1_heures = isset($row[$colonneXIndex]) ? floatval(str_replace(',', '.', $row[$colonneXIndex] ?? '0')) : 0;
        $s2_heures = isset($row[$colonneABIndex]) ? floatval(str_replace(',', '.', $row[$colonneABIndex] ?? '0')) : 0;
        
        if ($formateurP && $groupe && $module) {
            $affectations[] = ['formateur' => $formateurP, 'groupe' => $groupe, 'module' => $module, 'type' => 'presentiel', 's1_heures' => $s1_heures, 's2_heures' => $s2_heures, 'est_regional' => $est_regional];
        }
        if ($formateurS && $fusionGroupe && $module) {
            $affectations[] = ['formateur' => $formateurS, 'groupe' => $fusionGroupe, 'module' => $module, 'type' => 'synchrone', 's1_heures' => $s1_heures, 's2_heures' => $s2_heures, 'est_regional' => $est_regional];
        }

        // Construire la liste simple des formateurs (présentiel + synchrone)
        if ($formateurP) { $formateursListRaw[] = $formateurP; }
        if ($formateurS) { $formateursListRaw[] = $formateurS; }
    }
    
    $groupesList = array_values(array_unique($groupes)); sort($groupesList);
    $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);
    $formateursList = array_values(array_unique(array_filter($formateursListRaw)));
    sort($formateursList);
    
    $pdo->beginTransaction();

    // --- ÉTAPE 3 : SAUVEGARDE DES DONNÉES (LOGIQUE CORRIGÉE) ---
    
    // a) On supprime les anciennes données volatiles qui doivent être entièrement reconstruites
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee IN ('groupe', 'fusion_groupe', 'affectation')")
        ->execute([$etablissement_id]);
    
    // b) On insère les nouvelles listes
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    if(!empty($groupesList)) $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    if(!empty($fusionGroupesList)) $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
    if(!empty($affectations)) $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);
    // Mettre à jour la liste simple des formateurs via UPSERT pour éviter les doublons
    if(!empty($formateursList)) {
        $upsertFormateursStmt = $pdo->prepare(
            "INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json)
             VALUES (?, 'formateur', ?)
             ON DUPLICATE KEY UPDATE donnees_json = VALUES(donnees_json)"
        );
        $upsertFormateursStmt->execute([$etablissement_id, json_encode($formateursList)]);
    }
    
    // c) On utilise une requête UPSERT dédiée UNIQUEMENT pour 'groupe_mode' pour éviter les doublons
    if(!empty($groupeModes)) {
        $upsertModeStmt = $pdo->prepare(
            "INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) 
             VALUES (?, 'groupe_mode', ?)
             ON DUPLICATE KEY UPDATE donnees_json = VALUES(donnees_json)"
        );
        $upsertModeStmt->execute([$etablissement_id, json_encode($groupeModes)]);
    }
    
    // d) On met à jour les données d'avancement (qui utilise déjà un UPSERT)
    $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)");
    $avancementStmt->execute([$etablissement_id, $fileName, json_encode($dataRows)]);

    // Les données 'calendrier' ne sont pas modifiées par ce script.
    
    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Données de base et d\'avancement mises à jour avec succès.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>