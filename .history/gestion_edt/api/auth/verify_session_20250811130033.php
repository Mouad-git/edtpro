<?php
// Assurez-vous que le script commence bien une session
session_start(); 

// Inclure la connexion à la base de données
require_once '../../config/database.php';

header('Content-Type: application/json');

// Vérifier si un user_id existe dans la session
if (!isset($_SESSION['user_id'])) {
    // Si non, l'utilisateur n'est pas connecté
    echo json_encode(['success' => false, 'message' => 'Aucune session active.']);
    exit;
}

$userId = $_SESSION['user_id'];

// NOUVELLE ÉTAPE : Vérifier le statut de l'utilisateur DANS LA BASE DE DONNÉES
try {
    $stmt = $pdo->prepare("SELECT nom_complet, email, nom_etablissement, status FROM utilisateurs WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // LA VÉRIFICATION CRUCIALE EST ICI
    if ($user && $user['status'] === 'approved') {
        // Le statut est 'approved', tout va bien. On renvoie les données de l'utilisateur.
        echo json_encode([
            'success' => true,
            'userData' => [
                'nom_complet' => $user['nom_complet'],
                'email' => $user['email'],
                'nom_etablissement' => $user['nom_etablissement']
            ]
        ]);
    } else {
        // L'utilisateur n'existe plus OU son statut n'est plus 'approved' (il a été bloqué, rejeté, etc.)
        
        // 1. On détruit sa session serveur
        session_unset();
        session_destroy();

        // 2. On envoie une réponse d'échec au client pour le forcer à se déconnecter
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Votre session a expiré ou votre compte a été désactivé.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
?>