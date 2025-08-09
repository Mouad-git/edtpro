<?php
// api/data/get_base_data.php

require_once '../auth/session_check.php'; // Sécurité !
require_once '../../config/database.php'; // Connexion BDD

// On s'assure que la réponse sera bien interprétée comme du JSON
header('Content-Type: application/json');

$etablissement_id = $_SESSION['etablissement_id'];

try {
    // Initialiser la structure de données de sortie
    $appData = [
        'formateurs' => [],
        'groupes' => [],
        'fusionGroupes' => [],
        'espaces' => [],
        'affectations' => [],
        'calendar' => ['holidays' => '', 'vacations' => ''] // On initialise aussi le calendrier
    ];

    // 1. Récupérer les espaces
    $stmt_espaces = $pdo->prepare("SELECT nom_espace FROM espaces WHERE etablissement_id = ? ORDER BY nom_espace");
    $stmt_espaces->execute([$etablissement_id]);
    $appData['espaces'] = $stmt_espaces->fetchAll(PDO::FETCH_COLUMN);

    // 2. Récupérer toutes les données de base en une seule fois
    $stmt_base_data = $pdo->prepare("SELECT type_donnee, donnees_json FROM donnees_de_base WHERE etablissement_id = ?");
    $stmt_base_data->execute([$etablissement_id]);
    $baseDataResults = $stmt_base_data->fetchAll(PDO::FETCH_ASSOC);

    foreach ($baseDataResults as $row) {
        // Le `true` dans json_decode est crucial pour avoir des tableaux associatifs
        $data = json_decode($row['donnees_json'], true);

        // Si le JSON est invalide, $data sera `null`. On le transforme en tableau vide.
        if (json_last_error() !== JSON_ERROR_NONE) {
             error_log("JSON Error in get_base_data.php for type: " . $row['type_donnee'] . " - " . json_last_error_msg());
             $data = [];
        }
        
        // On utilise un 'match' qui est une version plus moderne et plus sûre du 'switch'
        match ($row['type_donnee']) {
            'formateur' => $appData['formateurs'] = $data,
            'groupe' => $appData['groupes'] = $data,
            'fusion_groupe' => $appData['fusionGroupes'] = $data,
            'affectation' => $appData['affectations'] = $data,
            'calendrier' => $appData['calendar'] = $data,
            default => null, // Ignorer les autres types
        };
    }

    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $appData]);

} catch (Exception $e) {
    http_response_code(500);
    // Enregistrer l'erreur dans les logs du serveur pour le débogage
    error_log('Erreur dans get_base_data.php: ' . $e->getMessage());
    // Envoyer un message générique à l'utilisateur
    echo json_encode(['success' => false, 'message' => 'Une erreur est survenue lors de la récupération des données de base.']);
}
?>