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

        // --- DÉBUT DE LA SECTION CORRIGÉE ET AMÉLIORÉE ---

        // ÉTAPE 1 : Vérifier si les clés 'X' et 'AB' existent.
        // C'est crucial si vos en-têtes de colonnes ne sont pas 'X' et 'AB'.
        if (!array_key_exists('X', $row) || !array_key_exists('AB', $row)) {
             // Si les clés n'existent pas, on arrête tout avec une erreur claire.
             // MODIFIEZ 'X' et 'AB' ici si vos en-têtes sont différents (ex: 'Heures S1').
            throw new Exception("Les en-têtes pour les colonnes des semestres (attendu : 'X' et 'AB') sont introuvables à la ligne " . ($rowNumber + 1) . ". Veuillez vérifier la ligne d'en-tête de votre fichier Excel.");
        }

        // ÉTAPE 2 : Rendre la lecture des nombres plus robuste.
        // On remplace la virgule par un point pour une conversion sûre.
        $valeurX_str = str_replace(',', '.', $row['X'] ?? '0');
        $valeurAB_str = str_replace(',', '.', $row['AB'] ?? '0');
        
        $valeurX = (float)$valeurX_str;
        $valeurAB = (float)$valeurAB_str;

        // ÉTAPE 3 : Détermination de la variable $semestre (logique inchangée)
        $semestre = 'S2'; 
        if ($valeurX > 0 && $valeurAB > 0) {
            $semestre = 'Annuel';
        } elseif ($valeurX > 0) {
            $semestre = 'S1';
        }
        
        // --- FIN DE LA SECTION CORRIGÉE ---


        if ($formateurP) $formateurs[] = $formateurP;
        if ($formateurS) $formateurs[] = $formateurS;
        if ($groupe) $groupes[] = $groupe;
        if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;

        if ($module) {
            if ($formateurP && $groupe) {
                $affectations[] = [
                    'formateur' => $formateurP, 
                    'groupe' => $groupe, 
                    'module' => $module, 
                    'type' => 'presentiel', 
                    'semestre' => $semestre // La propriété est maintenant garantie d'exister
                ];
            }
            if ($formateurS && $fusionGroupe) {
                $affectations[] = [
                    'formateur' => $formateurS, 
                    'groupe' => $fusionGroupe, 
                    'module' => $module, 
                    'type' => 'synchrone', 
                    'semestre' => $semestre // Et ici aussi
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