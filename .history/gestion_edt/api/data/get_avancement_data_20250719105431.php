<?php
// api/data/get_avancement_data.php

require_once '../auth/session_check.php'; // Sécurité !
require_once '../../config/database.php';   // Connexion BDD

$etablissement_id = $_SESSION['etablissement_id'];

try {
    // On va chercher dans la table donnees_avancement
    $stmt = $pdo->prepare("SELECT nom_fichier, donnees_json FROM donnees_avancement WHERE etablissement_id = ?");
    $stmt->execute([$etablissement_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // Données trouvées, on les renvoie
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'fileName' => $result['nom_fichier'],
            'data' => json_decode($result['donnees_json']) // On décode le JSON stocké pour le renvoyer comme un objet/tableau
        ]);
    } else {
        // Aucune donnée trouvée pour cet établissement
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Aucune donnée d\'avancement trouvée.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>