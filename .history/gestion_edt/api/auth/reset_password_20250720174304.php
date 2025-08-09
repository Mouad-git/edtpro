<?php
/**
 * API pour la Réinitialisation du Mot de Passe
 *
 * Ce script finalise le processus de mot de passe oublié.
 * Il reçoit un jeton (token) et un nouveau mot de passe.
 * 1. Valide les données reçues.
 * 2. Cherche un jeton correspondant et non expiré dans la base de données.
 * 3. Si le jeton est valide, il met à jour le mot de passe de l'utilisateur associé.
 * 4. Il supprime ensuite le jeton pour qu'il ne puisse pas être réutilisé.
 */

// On inclut la configuration de la base de données pour obtenir l'objet $pdo.
require_once '../../config/database.php';

// On indique que la réponse sera au format JSON.
header('Content-Type: application/json');

// Récupère les données JSON envoyées par le frontend.
$data = json_decode(file_get_contents("php://input"));

// --- Étape 1 : Validation des données entrantes ---
if (
    !isset($data->token) || empty($data->token) ||
    !isset($data->mot_de_passe) || empty($data->mot_de_passe) ||
    !isset($data->confirm_mot_de_passe) || empty($data->confirm_mot_de_passe)
) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Toutes les informations sont requises.']);
    exit;
}

if ($data->mot_de_passe !== $data->confirm_mot_de_passe) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Les mots de passe ne correspondent pas.']);
    exit;
}

if (strlen($data->mot_de_passe) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères.']);
    exit;
}

try {
    // --- Étape 2 : Vérifier la validité du jeton ---

    // On hashe le jeton reçu du formulaire pour le comparer avec celui, haché, dans la base de données.
    // C'est une mesure de sécurité importante.
    $token_hash = hash('sha256', $data->token);

    // On cherche le jeton dans la BDD. La requête vérifie trois choses :
    // 1. Le hash du jeton correspond.
    // 2. Le jeton n'a pas expiré (la date d'expiration est dans le futur).
    $stmt = $pdo->prepare(
        "SELECT utilisateur_id FROM password_resets 
         WHERE token_hash = ? AND expires_at > NOW()"
    );
    $stmt->execute([$token_hash]);
    $reset_request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset_request) {
        // Si aucune ligne n'est trouvée, le jeton est soit incorrect, soit expiré.
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Ce lien de réinitialisation est invalide ou a expiré. Veuillez refaire une demande.']);
        exit;
    }

    // --- Étape 3 : Le jeton est valide, on met à jour le mot de passe ---

    // On récupère l'ID de l'utilisateur associé à ce jeton.
    $utilisateur_id = $reset_request['utilisateur_id'];

    // On hashe le NOUVEAU mot de passe avant de le stocker.
    $new_hashed_password = password_hash($data->mot_de_passe, PASSWORD_DEFAULT);

    // On utilise une transaction pour garantir que les deux opérations (UPDATE et DELETE) réussissent.
    $pdo->beginTransaction();

    // a) Mettre à jour le mot de passe dans la table `utilisateurs`.
    $stmt_update = $pdo->prepare("UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?");
    $stmt_update->execute([$new_hashed_password, $utilisateur_id]);

    // b) Supprimer le jeton de la table `password_resets` pour qu'il ne puisse pas être réutilisé.
    $stmt_delete = $pdo->prepare("DELETE FROM password_resets WHERE utilisateur_id = ?");
    $stmt_delete->execute([$utilisateur_id]);
    
    // On valide la transaction.
    $pdo->commit();

    // --- Étape 4 : Envoyer une réponse de succès ---
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Votre mot de passe a été réinitialisé avec succès.']);

} catch (Exception $e) {
    // En cas d'erreur de base de données, on annule la transaction.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Erreur du serveur: ' . $e->getMessage()]);
}
?>