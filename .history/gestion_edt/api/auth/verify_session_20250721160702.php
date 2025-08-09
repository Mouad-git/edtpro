<?php
// api/auth/verify_session.php

require_once 'session_check.php'; // Ce fichier ne change pas, il fait déjà le gros du travail
require_once '../../config/database.php'; // On a besoin de la connexion BDD

try {
    // On récupère l'ID de l'utilisateur stocké dans la session
    $utilisateur_id = $_SESSION['utilisateur_id'];

    // On prépare une requête pour obtenir les informations de l'utilisateur
    $stmt = $pdo->prepare("SELECT nom_complet, email FROM utilisateurs WHERE id = ?");
    $stmt->execute([$utilisateur_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Cas très rare où l'utilisateur de la session n'existe plus dans la BDD
        throw new Exception("Utilisateur non trouvé.");
    }
    
    // Si tout va bien, on envoie une réponse de succès AVEC les données de l'utilisateur
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'userData' => [
            'nom' => $user['nom_complet'],
            'email' => $user['email']
        ]
    ]);

} catch (Exception $e) {
    // Si une erreur se produit, on invalide la session par sécurité
    http_response_code(403);
    session_destroy(); // Détruire la session en cas d'erreur grave
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>