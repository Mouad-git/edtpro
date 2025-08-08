<?php
// api/data/get_timetable.php

require_once '../auth/session_check.php';
require_once '../../config/database.php';

// On récupère la semaine demandée depuis l'URL (ex: ?semaine=2024-W46)
$weekValue = $_GET['semaine'] ?? '';

if (empty($weekValue)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aucune semaine spécifiée.']);
    exit;
}

$etablissement_id = $_SESSION['etablissement_id'];

try {
    $stmt = $pdo->prepare(
        "SELECT donnees_json FROM emplois_du_temps 
         WHERE etablissement_id = ? AND valeur_semaine = ?"
    );
    $stmt->execute([$etablissement_id, $weekValue]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // Un emploi du temps a été trouvé, on renvoie les données
        echo json_encode([
            'success' => true,
            'data' => json_decode($result['donnees_json'])
        ]);
    } else {
        // Aucun emploi du temps trouvé pour cette semaine pour cet établissement
        echo json_encode(['success' => false, 'message' => 'Aucun emploi du temps trouvé.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>