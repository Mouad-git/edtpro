<?php
//require_once '../../config/security.php'; // Toujours en premier !
// api/auth/verify_session.php
session_start(); // Toujours au début

// On vérifie UNIQUEMENT si l'utilisateur est authentifié.
if (!isset($_SESSION['utilisateur_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Session invalide.']);
    exit;
}

// Si l'utilisateur est authentifié, on renvoie ses données.
// Les pages JavaScript (admin.html, setup.html) pourront utiliser ces infos.
require_once '../../config/database.php';

try {
    $stmt = $pdo->prepare("SELECT nom_complet, email FROM utilisateurs WHERE id = ?");
    $stmt->execute([$_SESSION['utilisateur_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) { throw new Exception("Utilisateur de session non trouvé."); }
    
    echo json_encode([
        'success' => true,
        'userData' => ['nom' => $user['nom_complet'], 'email' => $user['email']]
    ]);

} catch (Exception $e) {
    http_response_code(403);
    session_destroy();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>