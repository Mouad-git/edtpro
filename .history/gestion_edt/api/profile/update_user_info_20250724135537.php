<?php
// api/profile/update_user_info.php
require_once '../auth/session_check.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$utilisateur_id = $_SESSION['utilisateur_id'];

if (empty($data['nom_complet']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Veuillez fournir un nom et un email valides.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE utilisateurs SET nom_complet = ?, email = ? WHERE id = ?");
    $stmt->execute([$data['nom_complet'], $data['email'], $utilisateur_id]);
    echo json_encode(['success' => true, 'message' => 'Informations personnelles mises à jour avec succès.']);
} catch (Exception $e) {
    // Gère le cas où l'email est déjà pris (contrainte UNIQUE dans la BDD)
    if ($e->getCode() == 23000) {
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé par un autre compte.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
    }
}
?>