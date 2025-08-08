<?php
// api/data/get_all_timetables.php

require_once '../auth/session_check.php'; // Sécurité !
require_once '../../config/database.php';   // Connexion BDD

$etablissement_id = $_SESSION['etablissement_id'];

try {
    $stmt = $pdo->prepare("SELECT valeur_semaine, donnees_json FROM emplois_du_temps WHERE etablissement_id = ? ORDER BY valeur_semaine DESC");
    $stmt->execute([$etablissement_id]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $historique = [];
    foreach ($results as $row) {
        $historique[] = [
            'semaine' => $row['valeur_semaine'],
            'emploiDuTemps' => json_decode($row['donnees_json'])
        ];
    }

    http_response_code(200);
    echo json_encode($historique); // On renvoie le tableau, même s'il est vide

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>