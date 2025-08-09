<?php
// index.php

// Démarrer la session pour vérifier si l'utilisateur est connecté
session_start();

// Si l'ID utilisateur existe dans la session, il est connecté.
if (isset($_SESSION['utilisateur_id'])) {
    // Rediriger vers le tableau de bord principal
    header('Location: public/admin.html');
} else {
    // Sinon, rediriger vers la page de connexion
    header('Location: public/login.html');
}
exit();