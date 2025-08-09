<?php
require_once '../auth/session_check.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$utilisateur_id = $_SESSION['utilisateur_id'];

// Validation...

$sql = "UPDATE utilisateurs SET nom_complet = ?, email = ?";
$params = [$data['nom_complet'], $data['email']];

// Logique pour la mise à jour conditionnelle du mot de passe
if (!empty($data['new_password'])) {
    if (strlen($data['new_password']) < 6) { /* ... erreur ... */ exit; }
    $sql .= ", mot_de_passe = ?";
    $params[] = password_hash($data['new_password'], PASSWORD_DEFAULT);
}

$sql .= " WHERE id = ?";
$params[] = $utilisateur_id;

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'message' => 'Informations mises à jour.']);
} catch (Exception $e) {
    // Gérer le cas où l'email est déjà pris (contrainte UNIQUE)
    if ($e->getCode() == 23000) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>