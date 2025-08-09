<?php
require_once '../auth/session_check_admin.php';
require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));
$userId = $data->user_id ?? null;

if (!$userId) { http_response_code(400); exit; }

$stmt = $pdo->prepare("UPDATE utilisateurs SET status = 'approved' WHERE id = ?");
$success = $stmt->execute([$userId]);

echo json_encode(['success' => $success]);
?>