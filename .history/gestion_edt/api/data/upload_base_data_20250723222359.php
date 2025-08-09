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
    
    $header = array_shift($rowsWithHeader); 
    $rows = $rowsWithHeader;

    if (empty($rows)) {
        throw new Exception("Le fichier Excel ne contient aucune ligne de données après l'en-tête.");
    }
    
    $formateurs = []; $groupes = []; $fusionGroupes = []; $affectations = [];

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
        // Lecture des colonnes existantes
        $formateurP = getFormattedName($row['U'] ?? null);
        $formateurS = getFormattedName($row['W'] ?? null);
        $groupe = trim($row['I'] ?? '');
        $fusionGroupe = trim($row['M'] ?? '');
        $module = trim($row['Q'] ?? '');

        // --- DÉBUT DE LA NOUVELLE LOGIQUE ---

        // 1. Lire les valeurs des colonnes X et AB pour déterminer le semestre.
        // floatval() gère les cellules vides (les convertit en 0) et les nombres.
        $valeurX = isset($row['X']) ? floatval($row['X']) : 0;
        $valeurAB = isset($row['AB']) ? floatval($row['AB']) : 0;
        
        // 2. Déterminer la chaîne de caractères du semestre
        $semestre = 's2'; // Valeur par défaut si seule AB > 0 ou si les deux sont à 0

        if ($valeurX > 0 && $valeurAB > 0) {
            $semestre = 'annual';
        } elseif ($valeurX > 0) {
            $semestre = 's1';
        }
        // Si $valeurAB > 0 et $valeurX <= 0, $semestre reste 's2' (notre valeur par défaut)

        // --- FIN DE LA NOUVELLE LOGIQUE ---

        // On continue de collecter les noms de formateurs et groupes comme avant
        if ($formateurP) $formateurs[] = $formateurP;
        if ($formateurS) $formateurs[] = $formateurS;
        if ($groupe) $groupes[] = $groupe;
        if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;

        // 3. On "enrichit" l'objet d'affectation avec la propriété 'semestre'
        if ($formateurP && $groupe && $module) {
            $affectations[] = [
                'formateur' => $formateurP,
                'groupe'    => $groupe,
                'module'    => $module,
                'type'      => 'presentiel',
                'semestre'  => $semestre // Ajout de la clé semestre
            ];
        }
        if ($formateurS && $fusionGroupe && $module) {
            $affectations[] = [
                'formateur' => $formateurS,
                'groupe'    => $fusionGroupe,
                'module'    => $module,
                'type'      => 'synchrone',
                'semestre'  => $semestre // Ajout de la clé semestre
            ];
        }
    }
    
    $formateursList = array_values(array_unique($formateurs)); sort($formateursList);
    $groupesList = array_values(array_unique($groupes)); sort($groupesList);
    $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);
    
    if (empty($formateursList) && empty($groupesList)) {
        throw new Exception("Aucun formateur ou groupe n'a été trouvé dans le fichier. Vérifiez que les colonnes I, U et W sont correctement remplies.");
    }
    
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ?")->execute([$etablissement_id]);
    
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    if(!empty($formateursList)) $insertStmt->execute([$etablissement_id, 'formateur', json_encode($formateursList)]);
    if(!empty($groupesList)) $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    if(!empty($fusionGroupesList)) $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
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