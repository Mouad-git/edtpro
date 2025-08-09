<?php
// /gestion_edt/index.php
session_start();

// Si l'utilisateur est déjà connecté, on l'envoie sur l'application
if (isset($_SESSION['utilisateur_id'])) {
    header("Location: public/admin.html");
} else {
// Sinon, on l'envoie sur la page de connexion
    header("Location: public/login.html");
}
exit;
?>