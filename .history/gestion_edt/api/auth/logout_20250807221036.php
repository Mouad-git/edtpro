<?php
require_once '../../config/security.php'; // Toujours en premier !
// api/auth/logout.php
session_start(); // On récupère la session existante
session_unset();   // On supprime toutes les variables de session
session_destroy(); // On détruit la session

// On redirige l'utilisateur vers la page de connexion
header("Location: ../../public/login.html");
exit;
?>