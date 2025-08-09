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
        'affectations' => [] // Ce tableau va être modifié
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
            case 'fusion_groupe':
                $appData['fusionGroupes'] = $data;
                break;
            case 'affectation':
                // --- MODIFICATION ICI ---
                // Au lieu de simplement assigner $data, on va traiter chaque affectation
                // pour ajouter les informations de semestre si elles ne sont pas déjà présentes
                // dans le JSON stocké.

                // Si les données sont stockées en JSON *avec* s1_heures et s2_heures, cette boucle est optionnelle.
                // Mais si elles viennent d'une autre source ou doivent être enrichies, on le fait ici.

                // Exemple: Si les données brutes de la BDD pour 'affectation' contiennent s1_heures et s2_heures,
                // vous devriez les récupérer directement. Comme elles viennent d'un JSON,
                // supposons qu'elles y sont déjà. Sinon, il faudrait une requête SQL différente ici.
                // Pour cet exemple, on suppose qu'elles sont dans le JSON.

                // On peut tout de même itérer pour s'assurer qu'elles existent et ont une valeur par défaut.
                foreach ($data as &$affectation_item) { // Notez le & pour référence
                    // S'assurer que les clés existent, sinon leur donner une valeur par défaut (0)
                    if (!isset($affectation_item['s1_heures'])) {
                        $affectation_item['s1_heures'] = 0;
                    }
                    if (!isset($affectation_item['s2_heures'])) {
                        $affectation_item['s2_heures'] = 0;
                    }
                    // Vous pouvez aussi ajouter des champs dérivés si vous préférez
                    // if ($affectation_item['s1_heures'] > 0 && $affectation_item['s2_heures'] > 0) {
                    //     $affectation_item['semestre'] = 'Annual';
                    // } elseif ($affectation_item['s1_heures'] > 0) {
                    //     $affectation_item['semestre'] = 'S1';
                    // } else {
                    //     $affectation_item['semestre'] = 'S2';
                    // }
                    // Cependant, il est plus flexible de laisser le JS faire ce calcul
                    // car il a le contexte du groupe sélectionné.
                }
                unset($affectation_item); // Libérer la référence

                $appData['affectations'] = $data; // Assigner le tableau modifié
                // --- FIN MODIFICATION ---
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