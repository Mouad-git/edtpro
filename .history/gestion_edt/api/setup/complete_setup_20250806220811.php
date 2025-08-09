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
require_once '../../includes/functions.php'; // <-- AJOUTÉ ICI


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

try {
    // On démarre une transaction. Si une seule requête échoue, tout sera annulé.
    $pdo->beginTransaction();

    // --- 1. Sauvegarde des données des formateurs (celles vérifiées par l'utilisateur) ---
    // --- 1. Sauvegarde des données des formateurs (celles vérifiées par l'utilisateur) ---

// a) On sauvegarde les détails complets dans la table `formateurs_details`
$stmtFormateursDetails = $pdo->prepare(
    "INSERT INTO formateurs_details (etablissement_id, nom_formateur, matricule, email, masse_horaire_statutaire) 
     VALUES (:etab_id, :nom, :matricule, :email, :masse)
     ON DUPLICATE KEY UPDATE matricule = VALUES(matricule), email = VALUES(email), masse_horaire_statutaire = VALUES(masse_horaire_statutaire)"
);
// On en profite pour extraire une simple liste des noms
$formateursNomsList = []; 
foreach ($data['formateursData'] as $formateur) {
    $stmtFormateursDetails->execute([
        ':etab_id' => $etablissement_id,
        ':nom' => $formateur['nom'],
        ':matricule' => $formateur['matricule'],
        ':email' => $formateur['email'],
        ':masse' => $formateur['masse_horaire']
    ]);
    $formateursNomsList[] = $formateur['nom']; // On ajoute le nom à notre liste simple
}

// b) On sauvegarde la simple liste de noms dans `donnees_de_base` pour la page admin
$pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'formateur'")->execute([$etablissement_id]);
if (!empty($formateursNomsList)) {
    $stmtFormateursBase = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'formateur', ?)");
    $stmtFormateursBase->execute([$etablissement_id, json_encode($formateursNomsList)]);
}
    
    // --- 2. Extraction et sauvegarde des données volatiles de l'Excel (Groupes, Affectations...) ---
    $groupes = []; $fusionGroupes = []; $affectations = [];
    
    $dataRows = array_slice($rawExcelDataWithHeader, 1);

    // Définition fixe des index de colonnes
    $groupeIndex = 8;            // Colonne I
    $fusionGroupeIndex = 12;     // Colonne M
    $moduleIndex = 16;           // Colonne Q
    $nomFormateurIndex = 20;     // Colonne U
    $nomFormateurSyncIndex = 22; // Colonne W

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
        
        $fusionGroupe = trim($row[$fusionGroupeIndex] ?? '');
        if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;
        
        $formateurP = getFormattedName($row[$nomFormateurIndex] ?? null);
        $formateurS = getFormattedName($row[$nomFormateurSyncIndex] ?? null);
        $module = trim($row[$moduleIndex] ?? '');

        if ($formateurP && $groupe && $module) $affectations[] = ['formateur' => $formateurP, 'groupe' => $groupe, 'module' => $module, 'type' => 'presentiel'];
        if ($formateurS && $fusionGroupe && $module) $affectations[] = ['formateur' => $formateurS, 'groupe' => $fusionGroupe, 'module' => $module, 'type' => 'synchrone'];
    }
    
    $groupesList = array_values(array_unique($groupes)); sort($groupesList);
    $fusionGroupesList = array_values(array_unique($fusionGroupes)); sort($fusionGroupesList);
    
    // On supprime les anciennes données volatiles avant de réinsérer les nouvelles
    $pdo->prepare("DELETE FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee IN ('groupe', 'fusion_groupe', 'affectation')")->execute([$etablissement_id]);
    $insertStmt = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, ?, ?)");
    if(!empty($groupesList)) $insertStmt->execute([$etablissement_id, 'groupe', json_encode($groupesList)]);
    if(!empty($fusionGroupesList)) $insertStmt->execute([$etablissement_id, 'fusion_groupe', json_encode($fusionGroupesList)]);
    if(!empty($affectations)) $insertStmt->execute([$etablissement_id, 'affectation', json_encode($affectations)]);

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
    // C'est l'étape qui casse la boucle de redirection.
    $_SESSION['etablissement_id'] = $etablissement_id;

    // Si on arrive ici, tout s'est bien passé. On valide toutes les opérations.
    $pdo->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Si une erreur est survenue, on annule toutes les opérations.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Erreur lors de la finalisation: " . $e->getMessage()]);
}
?>