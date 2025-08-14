<?php
// api/data/get_base_data.php

require_once '../auth/session_check.php'; // Sécurité !
require_once '../../config/database.php'; // Connexion BDD

header('Content-Type: application/json');
$etablissement_id = $_SESSION['etablissement_id'];

try {
    $appData = [
        'formateurs' => [],
        'groupes' => [],
        'fusionGroupes' => [],
        'espaces' => [],
        'affectations' => []
    ];

    // 1. Récupérer les espaces (inchangé)
    $stmt = $pdo->prepare("SELECT nom_espace FROM espaces WHERE etablissement_id = ? ORDER BY nom_espace");
    $stmt->execute([$etablissement_id]);
    $appData['espaces'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // 2. Récupérer les données de base
    $stmt = $pdo->prepare("SELECT type_donnee, donnees_json FROM donnees_de_base WHERE etablissement_id = ?");
    $stmt->execute([$etablissement_id]);
    $baseDataResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($baseDataResults as $row) {
        $data = json_decode($row['donnees_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
             error_log("Erreur JSON dans get_base_data.php pour type_donnee: " . $row['type_donnee']);
             continue; // On passe à la ligne suivante en cas d'erreur
        }

        switch ($row['type_donnee']) {
            case 'formateur':
                // --- CORRECTION 1 : Extraire uniquement les noms ---
                // On s'assure que $data est bien un tableau avant de continuer
                if (is_array($data)) {
                    // array_column extrait la valeur d'une clé spécifique de chaque objet dans un tableau
                    $appData['formateurs'] = array_column($data, 'nom');
                }
                break;

            case 'groupe':
                // --- CORRECTION 2 : Extraire uniquement les noms ---
                if (is_array($data)) {
                    $appData['groupes'] = array_column($data, 'nom');
                }
                break;

            case 'fusion_groupe':
                $appData['fusionGroupes'] = $data;
                break;

            case 'affectation':
                // Votre logique pour les affectations est excellente, on la garde.
                if (is_array($data)) {
                    foreach ($data as &$affectation_item) {
                        $affectation_item['s1_heures'] = isset($affectation_item['s1_heures']) ? (float)$affectation_item['s1_heures'] : 0;
                        $affectation_item['s2_heures'] = isset($affectation_item['s2_heures']) ? (float)$affectation_item['s2_heures'] : 0;
                        $affectation_item['est_regional'] = isset($affectation_item['est_regional']) ? (bool)$affectation_item['est_regional'] : false;
                    }
                    unset($affectation_item);
                    $appData['affectations'] = $data;
                }
                break;
        }
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $appData]);

} catch (Exception $e) {
    http_response_code(500);
    $errorMsg = 'Erreur lors de la récupération des données de base: ' . $e->getMessage();
    error_log($errorMsg);
    echo json_encode(['success' => false, 'message' => $errorMsg]);
}
?>