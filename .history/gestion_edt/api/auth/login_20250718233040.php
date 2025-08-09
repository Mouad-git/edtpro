<?php
// api/auth/login.php
require_once '../../config/database.php';

header('Content-Type: application/json');

// Démarrer la session en premier
session_start();

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['email']) || !isset($data['mot_de_passe'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email ou mot de passe manquant.']);
    exit;
}

$email = $data['email'];
$mot_de_passe = $data['mot_de_passe'];

try {
    // 1. Trouver l'utilisateur par email
    $sql_user = "SELECT id, mot_de_passe FROM utilisateurs WHERE email = ?";
    $stmt_user = $pdo->prepare($sql_user);
    $stmt_user->execute([$email]);
    $utilisateur = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // 2. Vérifier si l'utilisateur existe et si le mot de passe est correct
    if (!$utilisateur || !password_verify($mot_de_passe, $utilisateur['mot_de_passe'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Identifiants incorrects.']);
        exit;
    }

    // 3. Trouver l'établissement associé
    $sql_etab = "SELECT id FROM etablissements WHERE utilisateur_id = ?";
    $stmt_etab = $pdo->prepare($sql_etab);
    $stmt_etab->execute([$utilisateur['id']]);
    $etablissement = $stmt_etab->fetch(PDO::FETCH_ASSOC);

    // 4. Stocker les informations dans la session
    $_SESSION['utilisateur_id'] = $utilisateur['id'];
    $_SESSION['etablissement_id'] = $etablissement['id'];

    echo json_encode(['success' => true, 'message' => 'Connexion réussie.']);
} catch (PDOException $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur du serveur.']);
}
?>