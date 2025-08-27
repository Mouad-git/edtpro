<?php
/**
 * API pour Finaliser la Configuration Initiale
 *
 * Ce script reçoit les données consolidées des 4 étapes du formulaire de configuration,
 * les valide et les sauvegarde dans les tables appropriées de la base de données.
 * Il finalise la session de l'utilisateur et le marque comme ayant terminé la configuration.
 */

// On inclut le gardien de configuration. Il vérifie 'utilisateur_id' et nous fournit la variable $etablissement_id.
require_once '../auth/session_check_setup.php'; 
// La connexion à la BDD via $pdo est déjà incluse par le gardien.



header('Content-Type: application/json');

// Récupère les données JSON envoyées par le JavaScript
$data = json_decode(file_get_contents("php://input"), true);

// On récupère les ID depuis la session et le gardien
$utilisateur_id = $_SESSION['utilisateur_id'];
// $etablissement_id est disponible grâce au require_once de session_check_setup.php

// Validation pour s'assurer que les données principales sont bien présentes
if (empty($data['excelData']) || empty($data['formateursData']) || !isset($data['espaces'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Des données de configuration essentielles sont manquantes.']);
    exit;
}

// Récupération et nettoyage des données
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

try {
    // On démarre une transaction. Si une seule requête échoue, tout sera annulé.
    $pdo->beginTransaction();

    // --- 1. Sauvegarde des données des formateurs (celles vérifiées par l'utilisateur) ---
    $stmtFormateursDetails = $pdo->prepare(
        "INSERT INTO formateurs_details (etablissement_id, nom_formateur, matricule, email, masse_horaire_statutaire) 
         VALUES (:etab_id, :nom, :matricule, :email, :masse)
         ON DUPLICATE KEY UPDATE matricule = VALUES(matricule), email = VALUES(email), masse_horaire_statutaire = VALUES(masse_horaire_statutaire)"
    );
    $formateursNomsList = []; 
    foreach ($data['formateursData'] as $formateur) {
        $stmtFormateursDetails->execute([
            ':etab_id' => $etablissement_id,
            ':nom' => $formateur['nom'],
            ':matricule' => $formateur['matricule'],
            ':email' => $formateur['email'],
            ':masse' => $formateur['masse_horaire']
        ]);
        $formateursNomsList[] = $formateur['nom'];
    }

    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'formateur'")->execute([$etablissement_id]);
    if (!empty($formateursNomsList)) {
        $stmtFormateursBase = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'formateur', ?)");
        $stmtFormateursBase->execute([$etablissement_id, json_encode($formateursNomsList)]);
    }
    
    // --- 2. Extraction et sauvegarde des données volatiles de l'Excel (Groupes, Affectations...) ---
    $groupes = []; $fusionGroupes = []; $affectations = [];
    $groupeModes = [];
    
    $header = array_map('trim', $rawExcelDataWithHeader[0]);
    $dataRows = array_slice($rawExcelDataWithHeader, 1);

    function findColumnIndex($header, $possibleNames) {
        foreach ($possibleNames as $name) {
            $header_lower = array_map('strtolower', $header);
            $name_lower = strtolower($name);
            $index = array_search($name_lower, $header_lower);
            if ($index !== false) {
                return $index;
            }
        }
        return false;
    }

    $groupeIndex = 8;
    $fusionGroupeIndex = 12;
    $moduleIndex = 16;
    $nomFormateurIndex = 20;
    $nomFormateurSyncIndex = 22;
    $modeIndex = 15;

    // Indices fixes pour les colonnes X et AB (nouvelle logique de semestre)
    $colonneXIndex = 23;         // Colonne X (premier semestre) -> s1_heures
    $colonneABIndex = 27;        // Colonne AB (deuxième semestre) -> s2_heures
    
    // Indice fixe pour la colonne S (régional)
    $colonneSIndex = 18;         // Colonne S (régional: O=true, N=false)

    // --- DÉBUT DE LA SECTION CORRIGÉE ---
    foreach ($dataRows as $row) {
        $groupe = isset($row[$groupeIndex]) ? trim($row[$groupeIndex]) : '';
        if (empty($groupe)) {
            continue; // On ignore les lignes sans groupe, car elles sont inutilisables.
        }
        
        $fusionGroupe = isset($row[$fusionGroupeIndex]) ? trim($row[$fusionGroupeIndex]) : '';

        if (isset($row[$modeIndex])) {
            $modeRaw = trim((string)$row[$modeIndex]);
            if ($modeRaw !== '') {
                $groupes[] = $groupe; // On ajoute le groupe à la liste générale
                if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;
                $groupeModes[$groupe] = (stripos($modeRaw, 'ALT') !== false) ? 'Alterné' : 'Résidentiel';
            }
        }
        
        $formateurP = getFormattedName($row[$nomFormateurIndex] ?? null);
        $formateurS = getFormattedName($row[$nomFormateurSyncIndex] ?? null);
        $module = isset($row[$moduleIndex]) ? trim($row[$moduleIndex]) : '';

        // Extraction et validation robuste des données
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
        $s1_heures = 0;
        if (isset($row[$colonneXIndex]) && is_numeric(str_replace(',', '.', $row[$colonneXIndex]))) {
            $s1_heures = floatval(str_replace(',', '.', $row[$colonneXIndex]));
        }

        $s2_heures = 0;
        if (isset($row[$colonneABIndex]) && is_numeric(str_replace(',', '.', $row[$colonneABIndex]))) {
            $s2_heures = floatval(str_replace(',', '.', $row[$colonneABIndex]));
        }
        
        if ($formateurP && $groupe && $module) {
            $affectations[] = ['formateur' => $formateurP, 'groupe' => $groupe, 'module' => $module, 'type' => 'presentiel', 's1_heures' => $s1_heures, 's2_heures' => $s2_heures, 'est_regional' => $est_regional];
        }
        if ($formateurS && $fusionGroupe && $module) {
            $affectations[] = ['formateur' => $formateurS, 'groupe' => $fusionGroupe, 'module' => $module, 'type' => 'synchrone', 's1_heures' => $s1_heures, 's2_heures' => $s2_heures, 'est_regional' => $est_regional];
        }
    }
    // --- FIN DE LA SECTION CORRIGÉE ---
    
    $groupesList = array_values(array_unique($groupes)); sort($groupesList);
    $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);
    
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee IN ('groupe', 'fusion_groupe', 'affectation', 'groupe_mode')")->execute([$etablissement_id]);
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    if(!empty($groupesList)) $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    if(!empty($fusionGroupesList)) $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
    if(!empty($affectations)) $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);
    if(!empty($groupeModes)) $insertStmt->execute([$etablissement_id, 'groupe_mode', json_encode($groupeModes)]);

    // --- 3. Sauvegarde des données d'avancement ---
    $fileName = "Base initiale du " . date('d-m-Y');
    $avancementStmt = $pdo->prepare("INSERT INTO donnees_avancement (etablissement_id, nom_fichier, donnees_json) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nom_fichier = VALUES(nom_fichier), donnees_json = VALUES(donnees_json)");
    $avancementStmt->execute([$etablissement_id, $fileName, json_encode($dataRows)]);

    // --- 4. Sauvegarde des données du calendrier ---
    $stmtCalendar = $pdo->prepare("INSERT INTO calendrier (etablissement_id, jours_feries, vacances) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE jours_feries = VALUES(jours_feries), vacances = VALUES(vacances)");
    $stmtCalendar->execute([$etablissement_id, json_encode($holidays), json_encode($vacations)]);

    // --- 5. Sauvegarde des espaces ---
    $pdo->prepare("DELETE FROM espaces WHERE etablissement_id = ?")->execute([$etablissement_id]);
    $stmtEspaces = $pdo->prepare("INSERT INTO espaces (etablissement_id, nom_espace) VALUES (?, ?)");
    foreach ($espaces as $espace) {
        if (!empty(trim($espace))) {
            $stmtEspaces->execute([$etablissement_id, trim($espace)]);
        }
    }

    // --- 6. Marquer la configuration comme terminée ---
    $stmtSetup = $pdo->prepare("UPDATE utilisateurs SET is_setup_complete = TRUE WHERE id = ?");
    $stmtSetup->execute([$utilisateur_id]);
    
    // --- 7. FINALISER LA SESSION ---
    $_SESSION['etablissement_id'] = $etablissement_id;

    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Erreur lors de la finalisation: " . $e->getMessage()]);
}
?>