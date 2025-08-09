<?php
session_start();

// --- BLOC DE DÉBOGAGE TEMPORAIRE ---
header('Content-Type: text/plain'); // Pour voir la sortie brute
echo "Contenu de la session dans session_check_admin.php:\n";
var_dump($_SESSION);
// --- FIN DU BLOC DE DÉBOGAGE ---

if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit;
}
?>