<?php
// api/data/get_base_data.php

require_once '../auth/session_check.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

$etablissement_id = $_SESSION['etablissement_id'];

try {
    // Initialiser la structure de données de sortie avec des tableaux vides par défaut
    $appData = [
        'formateurs' => [], // Important : Toujours un tableau
        'groupes' => [],
        'fusionGroupes' => [],
        'espaces' => [],
        'affectations' => [],
        'calendrier' => ['holidays' => '', 'vacations' => '']
    ];

    // 1. Récupérer les espaces
    $stmtEspaces = $pdo->prepare("SELECT nom_espace FROM espaces WHERE etablissement_id = ? ORDER BY nom_espace");
    $stmtEspaces->execute([$etablissement_id]);
    $espacesResult = $stmtEspaces->fetchAll(PDO::FETCH_COLUMN);
    if ($espacesResult) {
        $appData['espaces'] = $espacesResult;
    }

    // 2. Récupérer toutes les données de base stockées en JSON
    $stmtBaseData = $pdo->prepare("SELECT type_donnee, donnees_json FROM donnees_de_base WHERE etablissement_id = ?");
    $stmtBaseData->execute([$etablissement_id]);
    $baseDataResults = $stmtBaseData->fetchAll(PDO::FETCH_ASSOC);

    foreach ($baseDataResults as $row) {
        // On décode le JSON en tableau associatif PHP
        $jsonData = json_decode($row['donnees_json'], true);

        // Si le décodage réussit et que le résultat est bien un tableau, on l'assigne.
        // C'est une sécurité contre les données JSON corrompues.
        if (is_array($jsonData)) {
            switch ($row['type_donnee']) {
                case 'formateur':
                    $appData['formateurs'] = $jsonData;
                    break;
                case 'groupe':
                    $appData['groupes'] = $jsonData;
                    break;
                case 'fusion_groupe':
                    $appData['fusionGroupes'] = $jsonData;
                    break;
                case 'affectation':
                    $appData['affectations'] = $jsonData;
                    break;
                case 'calendrier':
                    $appData['calendrier'] = $jsonData;
                    break;
            }
        }
    }
    
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $appData]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des données de base: ' . $e->getMessage()]);
}
?>