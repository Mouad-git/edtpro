<?php
// /gestion_edt/index.php


// On inclut le fichier qui contient la fonction secure_log() et les autres
require_once 'config/security.php';


// Démarrage de session sécurisé
// ...
// Démarrage de session sécurisé
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Régénération de l'ID de session pour prévenir la fixation de session
if (!isset($_SESSION['last_regeneration']) || (time() - $_SESSION['last_regeneration']) > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Vérification de l'IP pour détecter le vol de session
if (!isset($_SESSION['user_ip'])) {
    $_SESSION['user_ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
} elseif ($_SESSION['user_ip'] !== ($_SERVER['REMOTE_ADDR'] ?? 'unknown')) {
    // IP différente, possible vol de session
    secure_log('Tentative de vol de session détectée - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'WARNING');
    session_destroy();
    header("Location: public/login.html");
    exit;
}

// Vérification de l'User-Agent pour détecter le vol de session
if (!isset($_SESSION['user_agent'])) {
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
} elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown')) {
    // User-Agent différent, possible vol de session
    secure_log('Tentative de vol de session détectée - User-Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'), 'WARNING');
    session_destroy();
    header("Location: public/login.html");
    exit;
}

// Journalisation de l'accès
secure_log('Accès à l\'application - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 'INFO');

// Si l'utilisateur est déjà connecté, on l'envoie sur l'application
if (isset($_SESSION['utilisateur_id']) && isset($_SESSION['role'])) {
    // Vérification de la validité de la session
    if (!isset($_SESSION['session_expires']) || time() > $_SESSION['session_expires']) {
        // Session expirée
        secure_log('Session expirée pour l\'utilisateur ID: ' . $_SESSION['utilisateur_id'], 'INFO');
        session_destroy();
        header("Location: public/login.html");
        exit;
    }
    
    // Prolongation de la session
    $_SESSION['session_expires'] = time() + 3600; // 1 heure
    
    // Redirection selon le rôle
    if ($_SESSION['role'] === 'admin') {
        header("Location: public/admin_dashboard.html");
    } else {
        header("Location: public/emploi.html");
    }
} else {
    // Sinon, on l'envoie sur la page de connexion
    header("Location: public/login.html");
}

exit;
?>