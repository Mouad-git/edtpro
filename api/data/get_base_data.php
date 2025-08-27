<?php
// api/data/get_base_data.php

require_once '../auth/session_check.php'; // Sécurité !
require_once '../../config/database.php'; // Connexion BDD

// Assurez-vous que l'ID de l'établissement est bien défini dans la session
if (!isset($_SESSION['etablissement_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Session invalide ou expirée.']);
    exit();
}
$etablissement_id = $_SESSION['etablissement_id'];

try {
    // Initialiser la structure de données de sortie
    $appData = [
        'formateurs' => [],
        'groupes' => [],
        'fusionGroupes' => [],
        'espaces' => [],
        'affectations' => [],
        'jours_feries' => [], // Clé ajoutée pour la cohérence
        'vacances' => [],      // Clé ajoutée pour la cohérence
        'stages' => []
    ];

    // 1. Récupérer les espaces
    $stmt = $pdo->prepare("SELECT nom_espace FROM espaces WHERE etablissement_id = ? ORDER BY nom_espace");
    $stmt->execute([$etablissement_id]);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $appData['espaces'] = $results;

    // 2. Récupérer les données de base (formateurs, groupes, etc.)
    $stmt = $pdo->prepare("SELECT type_donnee, donnees_json FROM donnees_de_base WHERE etablissement_id = ?");
    $stmt->execute([$etablissement_id]);
    $baseDataResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($baseDataResults as $row) {
        $data = json_decode($row['donnees_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
             error_log("Erreur JSON dans get_base_data.php pour type_donnee: " . $row['type_donnee'] . " - " . json_last_error_msg());
             $data = [];
        }
        switch ($row['type_donnee']) {
            case 'formateur': $appData['formateurs'] = $data; break;
            case 'groupe': $appData['groupes'] = $data; break;
            case 'fusion_groupe': $appData['fusionGroupes'] = $data; break;
            case 'affectation':
                foreach ($data as &$affectation_item) {
                    $affectation_item['s1_heures'] = isset($affectation_item['s1_heures']) ? (float)$affectation_item['s1_heures'] : 0;
                    $affectation_item['s2_heures'] = isset($affectation_item['s2_heures']) ? (float)$affectation_item['s2_heures'] : 0;
                    $affectation_item['est_regional'] = isset($affectation_item['est_regional']) ? (bool)$affectation_item['est_regional'] : false;
                }
                unset($affectation_item);
                $appData['affectations'] = $data;
                break;
        }
    }

    // ====================================================================
    // ==   NOUVELLE SECTION : Récupération des données du calendrier    ==
    // ====================================================================
    
    $stmt_cal = $pdo->prepare("SELECT jours_feries, vacances FROM calendrier WHERE etablissement_id = ? LIMIT 1");
    $stmt_cal->execute([$etablissement_id]);
    $calendrier = $stmt_cal->fetch(PDO::FETCH_ASSOC);

    if ($calendrier) {
        // Décodez la chaîne JSON des jours fériés, avec une sécurité en cas d'erreur
        $jours_feries_data = json_decode($calendrier['jours_feries'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $appData['jours_feries'] = $jours_feries_data;
        } else {
            error_log("Erreur JSON (jours_feries) pour l'établissement " . $etablissement_id);
        }

        // Décodez la chaîne JSON des vacances, avec une sécurité en cas d'erreur
        $vacances_data = json_decode($calendrier['vacances'], true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $appData['vacances'] = $vacances_data;
        } else {
            error_log("Erreur JSON (vacances) pour l'établissement " . $etablissement_id);
        }
    }
    // Si aucun calendrier n'est trouvé pour l'établissement, les tableaux resteront vides, ce qui est correct.

    // ====================================================================
    // ==                   FIN DE LA NOUVELLE SECTION                   ==
    // ====================================================================

    // ====================================================================
    // ==        Récupération des données de votre table "stages"        ==
    // ====================================================================
    $stmt_stages = $pdo->prepare("
        SELECT id, groupe_nom, date_debut, date_fin 
        FROM stages 
        WHERE etablissement_id = ?
    ");
    $stmt_stages->execute([$etablissement_id]);
    $appData['stages'] = $stmt_stages->fetchAll(PDO::FETCH_ASSOC);
    // ====================================================================


    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $appData]);

} catch (Exception $e) {
    http_response_code(500);
    $errorMsg = 'Erreur lors de la récupération des données de base: ' . $e->getMessage();
    error_log($errorMsg);
    echo json_encode(['success' => false, 'message' => $errorMsg]);
}
?>