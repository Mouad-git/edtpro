<?php
// On s'assure que la session est démarrée.
// Si elle est déjà démarrée par un autre script, session_start() ne fait rien.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérification de la session et du rôle
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // 1. Indiquer que la réponse est du JSON
    header('Content-Type: application/json');
    
    // 2. Définir le code d'erreur
    http_response_code(403); // 403 Forbidden est parfait ici
    
    // 3. Envoyer un message d'erreur JSON valide
    echo json_encode([
        'success' => false, 
        'message' => 'Accès non autorisé. Vous devez être connecté en tant qu\'administrateur.'
    ]);
    
    // 4. Arrêter le script
    exit();
}
?>