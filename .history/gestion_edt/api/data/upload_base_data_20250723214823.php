<?php
// api/data/upload_base_data.php

require_once '../auth/session_check.php';
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

$etablissement_id = $_SESSION['etablissement_id'];

if (!isset($_FILES['excelFile']) || $_FILES['excelFile']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'upload du fichier.']);
    exit;
}

$filePath = $_FILES['excelFile']['tmp_name'];
$fileName = basename($_FILES['excelFile']['name']);

try {
    $spreadsheet = IOFactory::load($filePath);
    $sheet = $spreadsheet->getSheetByName('AvancementProgramme');
    if (!$sheet) {
        throw new Exception("La feuille 'AvancementProgramme' est introuvable. Veuillez vérifier le nom de la feuille dans votre fichier Excel.");
    }
    $rowsWithHeader = $sheet->toArray(null, true, true, true);
    
    // On retire la ligne d'en-tête pour ne pas la traiter comme une donnée
    $header = array_shift($rowsWithHeader); 
    $rows = $rowsWithHeader;

    if (empty($rows)) {
        throw new Exception("Le fichier Excel ne contient aucune ligne de données après l'en-tête.");
    }
    
    $formateurs = []; $groupes = []; $fusionGroupes = []; $affectations = [];

    // --- VOTRE FONCTION getFormattedName (inchangée) ---
    function getFormattedName($name) {
        if (!$name || !is_string($name)) return '';
        $words = array_filter(preg_split('/\s+/', trim($name)));
        if (count($words) === 0) return '';
        if (count($words) <= 1) return $name;
        if (count($words) === 2) return $words[1];
        $avantDernier = $words[count($words) - 2];
        $dernier = $words[count($words) - 1];
        return mb_strlen($avantDernier) < 4 ? "$avantDernier $dernier" : $dernier;
    }

    foreach ($rows as $rowNumber => $row) {
        // Lecture des données des colonnes (inchangé)
        $formateurP = getFormattedName($row['U'] ?? null);
        $formateurS = getFormattedName($row['W'] ?? null);
        $groupe = trim($row['I'] ?? '');
        $fusionGroupe = trim($row['M'] ?? '');
        $module = trim($row['Q'] ?? '');

        // NOUVEAU : Lecture des colonnes pour déterminer le semestre
        $valeurX = (float)($row['X'] ?? 0);   // Colonne X
        $valeurAB = (float)($row['AB'] ?? 0); // Colonne AB

        // NOUVEAU : Détermination de la variable $semestre
        $semestre = 'S2'; // Par défaut, on considère que c'est un module S2 (rouge)

        if ($valeurX > 0 && $valeurAB > 0) {
            $semestre = 'Annuel'; // Si X et AB > 0, c'est Annuel (orange)
        } elseif ($valeurX > 0) {
            $semestre = 'S1'; // Si seulement X > 0, c'est S1 (jaune)
        }

        if ($formateurP) $formateurs[] = $formateurP;
        if ($formateurS) $formateurs[] = $formateurS;
        if ($groupe) $groupes[] = $groupe;
        if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;

        // On vérifie qu'on a les infos minimales avant de créer une affectation
        if ($module) {
            // NOUVEAU : On ajoute la propriété 'semestre' à chaque objet d'affectation
            if ($formateurP && $groupe) {
                $affectations[] = [
                    'formateur' => $formateurP, 
                    'groupe' => $groupe, 
                    'module' => $module, 
                    'type' => 'presentiel', 
                    'semestre' => $semestre // On ajoute le semestre
                ];
            }
            if ($formateurS && $fusionGroupe) {
                $affectations[] = [
                    'formateur' => $formateurS, 
                    'groupe' => $fusionGroupe, 
                    'module' => $module, 
                    'type' => 'synchrone', 
                    'semestre' => $semestre // On ajoute aussi le semestre ici
                ];
            }
        }
    }
    
    $formateursList = array_values(array_unique($formateurs)); sort($formateursList);
    $groupesList = array_values(array_unique($groupes)); sort($groupesList);
    $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);
    
    if (empty($formateursList) && empty($groupesList)) {
        throw new Exception("Aucun formateur ou groupe n'a été trouvé dans le fichier. Vérifiez que les colonnes I, U et W sont correctement remplies.");
    }
    
    // --- Transaction de base de données (inchangée) ---
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ?")->execute([$etablissement_id]);
    
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    if(!empty($formateursList)) $insertStmt->execute([$etablissement_id, 'formateur', json_encode($formateursList)]);
    if(!empty($groupesList)) $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    if(!empty($fusionGroupesList)) $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
    
    // La variable $affectations contient maintenant les données enrichies
    if(!empty($affectations)) $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);

    $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)");
    $avancementStmt->execute([$etablissement_id, $fileName, json_encode($rows)]);

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>