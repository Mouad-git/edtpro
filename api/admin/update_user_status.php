<?php
// api/admin/update_user_status.php
require_once '../auth/session_check_admin.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"));
$userId = $data->user_id ?? null;
$newStatus = $data->new_status ?? null;

// ... (validation des données) ...

try {
    if ($newStatus === 'deleted') {
        // Logique de suppression
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
        $success = $stmt->execute([$userId]);
    } else {
        // Logique de mise à jour
        $dateColumn = null;
        if ($newStatus === 'approved') $dateColumn = 'date_approbation';
        if ($newStatus === 'rejected') $dateColumn = 'date_rejet';

        $sql = "UPDATE utilisateurs SET status = ?";
        $params = [$newStatus];
        if ($dateColumn) {
            $sql .= ", {$dateColumn} = NOW()";
        }
        $sql .= " WHERE id = ?";
        $params[] = $userId;
        
        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($params);
    }
    
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>