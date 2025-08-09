<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json'); // Assurez-vous que le header est bien là

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !isset($data->mot_de_passe)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email ou mot de passe manquant.']);
    exit;
}

try {
    // --- LA CORRECTION EST ICI ---
    $stmt = $pdo->prepare("SELECT id, mot_de_passe, is_verified FROM utilisateurs WHERE email = ?");
    $stmt->execute([$data->email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($data->mot_de_passe, $user['mot_de_passe'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect.']);
        exit;
    }

    // Maintenant, $user['is_verified'] existe et cette vérification ne plantera plus.
    if (!$user['is_verified']) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Votre compte n\'est pas encore vérifié. Veuillez consulter vos e-mails.']);
        exit;
    }

    // Le reste de votre code est correct
    $_SESSION['utilisateur_id'] = $user['id'];

    $stmt = $pdo->prepare("SELECT id, nom_etablissement FROM etablissements WHERE utilisateur_id = ?");
    $stmt->execute([$user['id']]);
    $etablissements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = count($etablissements);

    if ($count === 1) {
        $_SESSION['etablissement_id'] = $etablissements[0]['id'];
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'admin.html']);
    } elseif ($count > 1) {
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'select_etablissement.html']);
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Aucun établissement n\'est associé à ce compte.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur du serveur: ' . $e->getMessage()]);
}
?>