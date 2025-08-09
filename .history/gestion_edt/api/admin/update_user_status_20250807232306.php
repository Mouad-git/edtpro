<?php



require_once '../auth/session_check_admin.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"));
$userId = $data->user_id ?? null;
$newStatus = $data->new_status ?? null;

// Validation
if (!$userId || !in_array($newStatus, ['approved', 'rejected'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE utilisateurs SET status = ? WHERE id = ?");
    $success = $stmt->execute([$newStatus, $userId]);
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>