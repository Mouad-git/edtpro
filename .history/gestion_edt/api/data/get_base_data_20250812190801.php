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
        'groupeModes' => [],
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
        // Utiliser json_decode pour obtenir le tableau PHP
        $data = json_decode($row['donnees_json'], true);

        // S'assurer que json_decode a réussi
        if (json_last_error() !== JSON_ERROR_NONE) {
             // Gérer l'erreur ou logger un message
             error_log("Erreur JSON dans get_base_data.php pour type_donnee: " . $row['type_donnee'] . " - " . json_last_error_msg());
             $data = []; // Tableau vide par défaut en cas d'erreur
        }

        switch ($row['type_donnee']) {
            case 'formateur':
                $appData['formateurs'] = $data;
                break;
            case 'groupe':
                $appData['groupes'] = $data;
                break;
            case 'groupe_mode':
                // Normaliser les clés pour faciliter la correspondance côté front
                $normalized = [];
                if (is_array($data)) {
                    foreach ($data as $g => $m) { $normalized[strtoupper(trim((string)$g))] = $m; }
                }
                $appData['groupeModes'] = $normalized;
                break;
            case 'fusion_groupe':
                $appData['fusionGroupes'] = $data;
                break;
            case 'affectation':
                // Traiter les données d'affectation pour s'assurer que
                // les champs s1_heures, s2_heures et est_regional sont présents.
                // On suppose que ces champs sont déjà dans le JSON récupéré.
                foreach ($data as &$affectation_item) {
                    // S'assurer que les clés existent et sont des nombres, sinon leur donner une valeur par défaut (0)
                    $affectation_item['s1_heures'] = isset($affectation_item['s1_heures']) ? (float)$affectation_item['s1_heures'] : 0;
                    $affectation_item['s2_heures'] = isset($affectation_item['s2_heures']) ? (float)$affectation_item['s2_heures'] : 0;
                    // S'assurer que la clé est_regional existe et est un booléen, sinon lui donner une valeur par défaut (false)
                    // json_decode transforme true/false JSON en true/false PHP, mais on met quand même une sécurité.
                    $affectation_item['est_regional'] = isset($affectation_item['est_regional']) ? (bool)$affectation_item['est_regional'] : false;
                }
                unset($affectation_item); // Libérer la référence

                $appData['affectations'] = $data;
                break;
        }
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $appData]);

} catch (Exception $e) {
    http_response_code(500);
    // Améliorer le message d'erreur pour le débogage
    $errorMsg = 'Erreur lors de la récupération des données de base: ' . $e->getMessage();
    error_log($errorMsg); // Logger l'erreur côté serveur
    echo json_encode(['success' => false, 'message' => $errorMsg]);
}

?>