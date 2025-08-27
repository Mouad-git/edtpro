<?php
// api/auth/verify_session.php
session_start(); // Toujours au début

require_once '../../config/database.php';
header('Content-Type: application/json');

// Étape 1 : Vérifier si la variable de session existe
if (!isset($_SESSION['utilisateur_id'])) {
    http_response_code(401); // 401 Unauthorized est plus approprié
    echo json_encode(['success' => false, 'message' => 'Session invalide ou expirée.']);
    exit;
}

// Étape 2 : Vérifier le statut de l'utilisateur en base de données
try {
    $userId = $_SESSION['utilisateur_id'];

    // LA MODIFICATION CLÉ EST ICI : On sélectionne AUSSI la colonne 'status'
    $stmt = $pdo->prepare("SELECT nom_complet, email, status FROM utilisateurs WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // VÉRIFICATION DU STATUT EN PLUS DE L'EXISTENCE
    if ($user && $user['status'] === 'approved') {
        // Ensure etablissement_id is set in session for API compatibility
        if (!isset($_SESSION['etablissement_id'])) {
            $stmt_etab = $pdo->prepare("SELECT id FROM etablissements WHERE utilisateur_id = ? LIMIT 1");
            $stmt_etab->execute([$userId]);
            $etablissement = $stmt_etab->fetch(PDO::FETCH_ASSOC);
            
            if ($etablissement) {
                $_SESSION['etablissement_id'] = $etablissement['id'];
            } else {
                // User has no establishment - this shouldn't happen in normal flow
                session_unset();
                session_destroy();
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Configuration utilisateur incomplète.']);
                exit;
            }
        }
        
        // Le statut est bon, on peut continuer
        echo json_encode([
            'success' => true,
            'userData' => [
                'nom' => $user['nom_complet'], 
                'email' => $user['email']
            ]
        ]);
    } else {
        // Le statut n'est PAS 'approved' (il est 'blocked', 'rejected', ou l'utilisateur a été supprimé)
        // On doit forcer la déconnexion.
        
        // On détruit la session côté serveur
        session_unset();
        session_destroy();
        
        // On renvoie une erreur pour que le client (JavaScript) redirige vers la page de connexion
        http_response_code(403); // 403 Forbidden est approprié ici
        echo json_encode(['success' => false, 'message' => 'Votre compte a été désactivé ou bloqué.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    session_destroy(); // Détruire la session en cas d'erreur serveur aussi
    error_log($e->getMessage()); // Enregistrer l'erreur pour le développeur
    echo json_encode(['success' => false, 'message' => 'Erreur serveur lors de la vérification de la session.']);
}
?>