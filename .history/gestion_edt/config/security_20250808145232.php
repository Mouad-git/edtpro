<?php
/**
 * Fichier de configuration de sécurité pour l'environnement de production.
 * À inclure au début de chaque script PHP accessible publiquement.
 */

// 1. Désactiver l'affichage des erreurs à l'utilisateur
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// 2. Activer l'enregistrement des erreurs dans un fichier de log
ini_set('log_errors', 1);

// 3. Définir le chemin du fichier de log sécurisé
$log_path = dirname(__DIR__) . '/logs/php_errors.log';
if (!is_dir(dirname($log_path))) {
    mkdir(dirname($log_path), 0755, true);
}
ini_set('error_log', $log_path);

// 4. Headers de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data: https:; font-src \'self\'; connect-src \'self\';');

// 5. Configuration des sessions sécurisées
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// 6. Protection contre les attaques par timing
if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        if (strlen($known_string) != strlen($user_string)) {
            return false;
        }
        $res = $known_string ^ $user_string;
        $ret = 0;
        for ($i = strlen($res) - 1; $i >= 0; $i--) {
            $ret |= ord($res[$i]);
        }
        return !$ret;
    }
}

// 7. Fonction de nettoyage des entrées
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// 8. Fonction de validation CSRF
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 9. Fonction de rate limiting
function check_rate_limit($key, $max_attempts = 5, $time_window = 300) {
    $current_time = time();
    $attempts_file = dirname(__DIR__) . '/logs/rate_limit_' . md5($key) . '.log';
    
    if (file_exists($attempts_file)) {
        $attempts = json_decode(file_get_contents($attempts_file), true);
        $attempts = array_filter($attempts, function($timestamp) use ($current_time, $time_window) {
            return ($current_time - $timestamp) < $time_window;
        });
    } else {
        $attempts = [];
    }
    
    if (count($attempts) >= $max_attempts) {
        return false;
    }
    
    $attempts[] = $current_time;
    file_put_contents($attempts_file, json_encode($attempts));
    return true;
}

// 10. Fonction de journalisation sécurisée
function secure_log($message, $level = 'INFO') {
    $log_file = dirname(__DIR__) . '/logs/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $log_entry = "[$timestamp] [$level] [$ip] [$user_agent] $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// 11. Protection contre les injections SQL
function escape_sql_input($pdo, $input) {
    if (is_array($input)) {
        return array_map(function($item) use ($pdo) {
            return escape_sql_input($pdo, $item);
        }, $input);
    }
    return $pdo->quote($input);
}

// 12. Validation des emails
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) && 
           preg_match('/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/', $email);
}

// 13. Validation des mots de passe
function validate_password($password) {
    // Au moins 8 caractères, une majuscule, une minuscule, un chiffre
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[a-z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

// 14. Fonction de nettoyage des fichiers de log
function cleanup_old_logs($days = 30) {
    $log_dir = dirname(__DIR__) . '/logs/';
    $files = glob($log_dir . '*.log');
    $cutoff = time() - ($days * 24 * 60 * 60);
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
        }
    }
}

// 15. Nettoyage automatique des logs (une fois par jour)
if (!file_exists(dirname(__DIR__) . '/logs/.last_cleanup') || 
    (time() - filemtime(dirname(__DIR__) . '/logs/.last_cleanup')) > 86400) {
    cleanup_old_logs();
    file_put_contents(dirname(__DIR__) . '/logs/.last_cleanup', time());
}
?>