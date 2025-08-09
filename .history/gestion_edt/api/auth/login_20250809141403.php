<?php
/**
 * API pour la Connexion d'un Utilisateur
 *
 * Ce script gère le processus de connexion de manière sécurisée.
 * Il vérifie les identifiants, le statut du compte, le rôle, et l'état de la configuration
 * avant de rediriger l'utilisateur vers la page appropriée.
 */

// Si votre fichier security.php ne le fait pas déjà, décommentez la ligne suivante.
// session_start();

require_once '../../config/security.php'; // Gère les fonctions de sécurité et le démarrage de session.
require_once '../../config/database.php';

header('Content-Type: application/json');

// Vérification de la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Rate limiting pour prévenir les attaques par force brute
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!check_rate_limit("login_$ip", 50, 300)) { // 5 tentatives max en 5 minutes
    secure_log("Rate limit dépassé pour la connexion - IP: $ip", 'WARNING');
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Trop de tentatives de connexion. Veuillez réessayer dans 5 minutes.']);
    exit;
}

$data = json_decode(file_get_contents("php://input"));

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
    exit;
}

// Validation et nettoyage des entrées
$email = isset($data->email) ? sanitize_input($data->email) : '';
$password = isset($data->mot_de_passe) ? $data->mot_de_passe : '';

if (!validate_email($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Veuillez fournir un email et un mot de passe valides.']);
    exit;
}

try {
    // Recherche de l'utilisateur avec toutes les informations nécessaires
    $stmt = secure_query($pdo, "SELECT id, mot_de_passe, is_verified, is_setup_complete, status, role, nom_complet FROM utilisateurs WHERE email = ?", [$email]);
    $user = $stmt->fetch();

    // --- CORRECTION MAJEURE : VÉRIFICATION DU MOT DE PASSE ---
    // La fonction password_verify() est la SEULE méthode correcte et sécurisée pour vérifier un mot de passe haché.
    // Elle est déjà protégée contre les attaques par timing.
    if (!$user || !password_verify($password, $user['mot_de_passe'])) {
        secure_log("Tentative de connexion échouée pour l'email: $email - IP: $ip", 'WARNING');
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect']);
        exit;
    }
    
    // Si on arrive ici, l'authentification est réussie.
    secure_log("Connexion réussie pour l'utilisateur ID: {$user['id']} - IP: $ip", 'INFO');
    
    // Régénération de l'ID de session pour prévenir la fixation de session
    session_regenerate_id(true);
    
    // Configuration de la session avec les informations de l'utilisateur
    $_SESSION['utilisateur_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['nom_complet'] = $user['nom_complet'];
    $_SESSION['last_activity'] = time();
    $_SESSION['user_ip'] = $ip;
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // --- GESTION DES RÔLES ET STATUTS ---

    if ($user['role'] === 'admin') {
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'admin_dashboard.html', 'csrf_token' => $_SESSION['csrf_token']]);
        exit;
    }

    if ($user['status'] === 'pending') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Votre compte est en attente d\'approbation.']);
        exit;
    }
    if ($user['status'] === 'rejected') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Votre inscription a été refusée.']);
        exit;
    }
    if (!$user['is_verified']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Votre compte n\'est pas encore vérifié.']);
        exit;
    }

    // --- GESTION DE LA REDIRECTION POUR LES DIRECTEURS APPROUVÉS ET VÉRIFIÉS ---

    if (!$user['is_setup_complete']) {
        $stmt_etab = secure_query($pdo, "SELECT id FROM etablissements WHERE utilisateur_id = ? LIMIT 1", [$user['id']]);
        $etablissement = $stmt_etab->fetch();
        if ($etablissement) {
            $_SESSION['etablissement_id'] = $etablissement['id'];
        }
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'setup.html', 'csrf_token' => $_SESSION['csrf_token']]);
        exit;
    }
    
    $stmt_etabs = secure_query($pdo, "SELECT id FROM etablissements WHERE utilisateur_id = ?", [$user['id']]);
    $etablissements = $stmt_etabs->fetchAll();

    if (count($etablissements) === 1) {
        $_SESSION['etablissement_id'] = $etablissements[0]['id'];
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'emploi.html', 'csrf_token' => $_SESSION['csrf_token']]);
    } else {
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'select_etablissement.html', 'csrf_token' => $_SESSION['csrf_token']]);
    }

} catch (Exception $e) {
    secure_log("Erreur lors de la connexion: " . $e->getMessage() . " - IP: $ip", 'ERROR');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur du serveur. Veuillez réessayer.']);
}
?>