<?php
// api/data/get_base_data.php

require_once '../auth/session_check.php'; // Sécurité !
require_once '../../config/database.php'; // Connexion BDD

$etablissement_id = $_SESSION['etablissement_id'];

try {
    // Initialiser la structure de données de sortie
    $appData = [
        'formateurs' => [],
        'groupes' => [],
        'fusionGroupes' => [],
        'espaces' => [],
        'affectations' => []
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
        switch ($row['type_donnee']) {
            case 'formateur':
                $appData['formateurs'] = $data;
                break;
            case 'groupe':
                $appData['groupes'] = $data;
                break;
            case 'fusion_groupe':
                $appData['fusionGroupes'] = $data;
                break;
            case 'affectation':
                $appData['affectations'] = $data;
                break;
        }
    }
    
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $appData]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des données de base: ' . $e->getMessage()]);
}
?>