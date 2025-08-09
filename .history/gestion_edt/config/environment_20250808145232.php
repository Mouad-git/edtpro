<?php
/**
 * Configuration d'environnement sécurisée
 * Ce fichier gère les variables d'environnement pour différents environnements
 */

// Détection de l'environnement
$environment = $_ENV['APP_ENV'] ?? 'development';

// Configuration par environnement
switch ($environment) {
    case 'production':
        // Production - variables d'environnement strictes
        $_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? 'localhost';
        $_ENV['DB_NAME'] = $_ENV['DB_NAME'] ?? 'gestion_edt';
        $_ENV['DB_USER'] = $_ENV['DB_USER'] ?? 'root';
        $_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? '';
        
        $_ENV['SMTP_HOST'] = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $_ENV['SMTP_USER'] = $_ENV['SMTP_USER'] ?? 'edtproservice@gmail.com';
        $_ENV['SMTP_PASS'] = $_ENV['SMTP_PASS'] ?? 'gvqt gbea qkia gkoo';
        
        // Sécurité renforcée en production
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        error_reporting(0);
        break;
        
    case 'staging':
        // Staging - configuration intermédiaire
        $_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? 'localhost';
        $_ENV['DB_NAME'] = $_ENV['DB_NAME'] ?? 'gestion_edt_staging';
        $_ENV['DB_USER'] = $_ENV['DB_USER'] ?? 'root';
        $_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? '';
        
        $_ENV['SMTP_HOST'] = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $_ENV['SMTP_USER'] = $_ENV['SMTP_USER'] ?? 'edtproservice@gmail.com';
        $_ENV['SMTP_PASS'] = $_ENV['SMTP_PASS'] ?? 'gvqt gbea qkia gkoo';
        
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
        break;
        
    default:
        // Development - configuration de développement
        $_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? 'localhost';
        $_ENV['DB_NAME'] = $_ENV['DB_NAME'] ?? 'gestion_edt';
        $_ENV['DB_USER'] = $_ENV['DB_USER'] ?? 'root';
        $_ENV['DB_PASSWORD'] = $_ENV['DB_PASSWORD'] ?? '';
        
        $_ENV['SMTP_HOST'] = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
        $_ENV['SMTP_USER'] = $_ENV['SMTP_USER'] ?? 'edtproservice@gmail.com';
        $_ENV['SMTP_PASS'] = $_ENV['SMTP_PASS'] ?? 'gvqt gbea qkia gkoo';
        
        // Affichage des erreurs en développement
        ini_set('display_errors', 1);
        ini_set('log_errors', 1);
        error_reporting(E_ALL);
        break;
}

// Configuration de sécurité commune
$_ENV['APP_KEY'] = $_ENV['APP_KEY'] ?? bin2hex(random_bytes(32));
$_ENV['SESSION_SECURE'] = $_ENV['SESSION_SECURE'] ?? ($environment === 'production' ? '1' : '0');
$_ENV['SESSION_HTTPONLY'] = $_ENV['SESSION_HTTPONLY'] ?? '1';
$_ENV['SESSION_SAMESITE'] = $_ENV['SESSION_SAMESITE'] ?? 'Strict';

// Configuration des logs
$_ENV['LOG_LEVEL'] = $_ENV['LOG_LEVEL'] ?? ($environment === 'production' ? 'ERROR' : 'INFO');
$_ENV['LOG_RETENTION_DAYS'] = $_ENV['LOG_RETENTION_DAYS'] ?? '30';

// Configuration du rate limiting
$_ENV['RATE_LIMIT_LOGIN'] = $_ENV['RATE_LIMIT_LOGIN'] ?? '5';
$_ENV['RATE_LIMIT_LOGIN_WINDOW'] = $_ENV['RATE_LIMIT_LOGIN_WINDOW'] ?? '300';
$_ENV['RATE_LIMIT_REGISTER'] = $_ENV['RATE_LIMIT_REGISTER'] ?? '3';
$_ENV['RATE_LIMIT_REGISTER_WINDOW'] = $_ENV['RATE_LIMIT_REGISTER_WINDOW'] ?? '3600';

// Configuration de la session
$_ENV['SESSION_LIFETIME'] = $_ENV['SESSION_LIFETIME'] ?? '3600';
$_ENV['SESSION_REGEN_TIME'] = $_ENV['SESSION_REGEN_TIME'] ?? '300';

// Validation des variables critiques
$required_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'SMTP_HOST', 'SMTP_USER', 'SMTP_PASS'];
foreach ($required_vars as $var) {
    if (empty($_ENV[$var])) {
        error_log("Variable d'environnement manquante: $var");
        if ($environment === 'production') {
            die("Configuration d'environnement incomplète");
        }
    }
}

// Fonction pour obtenir une variable d'environnement avec valeur par défaut
function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

// Fonction pour vérifier si on est en production
function is_production() {
    return env('APP_ENV') === 'production';
}

// Fonction pour vérifier si on est en développement
function is_development() {
    return env('APP_ENV') === 'development';
}

// Fonction pour obtenir la configuration de base de données
function get_db_config() {
    return [
        'host' => env('DB_HOST'),
        'dbname' => env('DB_NAME'),
        'username' => env('DB_USER'),
        'password' => env('DB_PASSWORD')
    ];
}

// Fonction pour obtenir la configuration SMTP
function get_smtp_config() {
    return [
        'host' => env('SMTP_HOST'),
        'username' => env('SMTP_USER'),
        'password' => env('SMTP_PASS'),
        'port' => 465,
        'encryption' => 'ssl'
    ];
}
?> 