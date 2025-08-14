<?php
// api/auth/login.php
require_once '../../config/security.php';
require_once '../../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!check_rate_limit("login_$ip", 5, 300)) {
    secure_log("Rate limit dépassé pour la connexion - IP: $ip", 'WARNING');
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Trop de tentatives de connexion. Veuillez réessayer dans 5 minutes.']);
    exit;
}

$input = file_get_contents("php://input");
$data = json_decode($input);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
    exit;
}

$email = isset($data->email) ? sanitize_input($data->email) : '';
$password = isset($data->mot_de_passe) ? $data->mot_de_passe : '';

if (!validate_email($email)) {
    secure_log("Tentative de connexion avec email invalide: $email - IP: $ip", 'WARNING');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Adresse email invalide']);
    exit;
}

if (empty($password)) {
    secure_log("Tentative de connexion sans mot de passe - IP: $ip", 'WARNING');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mot de passe requis']);
    exit;
}

try {
    $stmt = secure_query($pdo, "SELECT id, mot_de_passe, is_verified, is_setup_complete, status, role, nom_complet FROM utilisateurs WHERE email = ?", [$email]);
    $user = $stmt->fetch();

    // Vérification sécurisée du mot de passe. 'password_verify' est la seule fonction nécessaire.
    if (!$user || !password_verify($password, $user['mot_de_passe'])) {
        secure_log("Tentative de connexion échouée pour l'email: $email - IP: $ip", 'WARNING');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect']);
        exit;
    }
    
    secure_log("Connexion réussie pour l'utilisateur ID: {$user['id']} - IP: $ip", 'INFO');
    
    session_regenerate_id(true);
    
    $_SESSION['utilisateur_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['nom_complet'] = $user['nom_complet'];
    $_SESSION['session_expires'] = time() + 3600;
    $_SESSION['last_activity'] = time();
    $_SESSION['user_ip'] = $ip;
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    // --- GESTION DES RÔLES ET STATUTS ---

    if ($user['role'] === 'admin') {
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'admin_dashboard.html', 'csrf_token' => $_SESSION['csrf_token']]);
        exit;
    }

    // --- LA CORRECTION EST ICI : Utilisation d'un switch pour plus de clarté ---
    // Si l'utilisateur est un 'director', on vérifie son statut.
    switch ($user['status']) {
        case 'blocked':
            // NOUVELLE VÉRIFICATION : Le compte est bloqué
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Votre compte a été bloqué par un administrateur.']);
            exit; // Arrête le script ici

        case 'pending':
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Votre compte est en attente d\'approbation.']);
            exit;

        case 'rejected':
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Votre inscription a été refusée.']);
            exit;
            
        case 'approved':
            // Le statut est bon, le script peut continuer vers la logique de redirection
            break; 
            
        default:
             // Cas de sécurité pour tout autre statut imprévu
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Le statut de votre compte ne permet pas la connexion.']);
            exit;
    }
    
    // Si on arrive ici, c'est que le statut était 'approved'.
    if (!$user['is_verified']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Votre compte n\'est pas encore vérifié.']);
        exit;
    }

    // --- GESTION DE LA REDIRECTION POUR LES DIRECTEURS APPROUVÉS ---

    if (!$user['is_setup_complete']) {
        $stmt_etab = secure_query($pdo, "SELECT id FROM etablissements WHERE utilisateur_id = ? LIMIT 1", [$user['id']]);
        $etablissement = $stmt_etab->fetch();
        if ($etablissement) {
            $_SESSION['etablissement_id'] = $etablissement['id'];
        }
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'setup.html', 'csrf_token' => $_SESSION['csrf_token']]);
        exit;
    }
    
    $stmt = secure_query($pdo, "SELECT id FROM etablissements WHERE utilisateur_id = ?", [$user['id']]);
    $etablissements = $stmt->fetchAll();

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