<?php
// api/auth/login.php

session_start();
require_once '../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !filter_var($data->email, FILTER_VALIDATE_EMAIL) || !isset($data->mot_de_passe)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Veuillez fournir un email et un mot de passe valides.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, mot_de_passe, is_verified, is_setup_complete, status, role FROM utilisateurs WHERE email = ?");
    $stmt->execute([$data->email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($data->mot_de_passe, $user['mot_de_passe'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect.']);
        exit;
    }
    
    // On met les infos de base dans la session
    $_SESSION['utilisateur_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];

    // --- GESTION DES RÔLES ET STATUTS ---

    // Cas 1 : L'utilisateur est un administrateur
    if ($user['role'] === 'admin') {
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'admin_dashboard.html']);
        exit;
    }

    // Si on arrive ici, l'utilisateur est un 'director'. On vérifie son statut.
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

    // --- GESTION DE LA REDIRECTION POUR LES DIRECTEURS ---

    if (!$user['is_setup_complete']) {
        $stmt_etab = $pdo->prepare("SELECT id FROM etablissements WHERE utilisateur_id = ? LIMIT 1");
        $stmt_etab->execute([$user['id']]);
        $etablissement = $stmt_etab->fetch(PDO::FETCH_ASSOC);
        if ($etablissement) {
            $_SESSION['etablissement_id'] = $etablissement['id'];
        }
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'setup.html']);
        exit;
    }
    
    // Le directeur est déjà configuré, on vérifie s'il a un ou plusieurs établissements
    $stmt = $pdo->prepare("SELECT id FROM etablissements WHERE utilisateur_id = ?");
    $stmt->execute([$user['id']]);
    $etablissements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($etablissements) === 1) {
        $_SESSION['etablissement_id'] = $etablissements[0]['id'];
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'emploi.html']);
    } else { // >= 2 ou 0 (cas anormal)
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'select_etablissement.html']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur du serveur: ' . $e->getMessage()]);
}
?>