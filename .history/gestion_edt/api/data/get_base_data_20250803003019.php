<?php
// api/data/get_base_data.php

require_once '../auth/session_check.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

$etablissement_id = $_SESSION['etablissement_id'];

try {
    // Initialiser la structure de données avec des valeurs par défaut
    $appData = [
        'formateurs' => [], // Sera un tableau d'objets
        'groupes' => [],
        'fusionGroupes' => [],
        'espaces' => [],
        'affectations' => [],
        'calendar' => ['holidays' => '', 'vacations' => '']
    ];

    // 1. Récupérer les espaces
    $stmtEspaces = $pdo->prepare("SELECT nom_espace FROM espaces WHERE etablissement_id = ? ORDER BY nom_espace");
    $stmtEspaces->execute([$etablissement_id]);
    $appData['espaces'] = $stmtEspaces->fetchAll(PDO::FETCH_COLUMN);

    // 2. Récupérer toutes les autres données de base
    $stmtData = $pdo->prepare("SELECT type_donnee, donnees_json FROM donnees_de_base WHERE etablissement_id = ?");
    $stmtData->execute([$etablissement_id]);
    $baseDataResults = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    foreach ($baseDataResults as $row) {
        $data = json_decode($row['donnees_json'], true);
        if (json_last_error() !== JSON_ERROR_NONE) { continue; } // On ignore les JSON invalides

        switch ($row['type_donnee']) {
            case 'formateur':
                // --- CORRECTION CRUCIALE ICI ---
                // On s'assure que 'formateurs' est bien un tableau d'objets.
                // Si ce n'est pas le cas (ancienne sauvegarde), on le transforme.
                if (isset($data[0]) && is_string($data[0])) {
                    // C'est une ancienne sauvegarde (simple liste de noms)
                    $formattedFormateurs = [];
                    foreach ($data as $nom) {
                        // On crée une structure d'objet par défaut
                        $formattedFormateurs[] = [
                            'nom' => $nom,
                            'matricule' => 'N/A',
                            'email' => '',
                            'masse_horaire' => 910
                        ];
                    }
                    $appData['formateurs'] = $formattedFormateurs;
                } else {
                    // C'est une nouvelle sauvegarde, les données sont déjà au bon format
                    $appData['formateurs'] = $data;
                }
                break;
                // --- FIN DE LA CORRECTION ---

            case 'groupe':
                $appData['groupes'] = $data;
                break;
            case 'fusion_groupe':
                $appData['fusionGroupes'] = $data;
                break;
            case 'affectation':
                $appData['affectations'] = $data;
                break;
            case 'calendrier':
                $appData['calendar'] = $data;
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