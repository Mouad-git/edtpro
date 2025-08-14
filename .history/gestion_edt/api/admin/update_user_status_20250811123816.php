<?php
// api/admin/update_user_status.php
require_once '../auth/session_check_admin.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"));
$userId = $data->user_id ?? null;
$newStatus = $data->new_status ?? null;

if (!$userId || !$newStatus) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données manquantes.']);
    exit;
}

try {
    // CAS 1 : Suppression définitive
    if ($newStatus === 'deleted') {
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
        $stmt->execute([$userId]);

        echo json_encode(['success' => $stmt->rowCount() > 0, 'message' => 'Utilisateur supprimé.']);

    // CAS 2 : Mise à jour de statut
    } else {
        $allowed_statuses = ['approved', 'rejected', 'blocked'];
        if (!in_array($newStatus, $allowed_statuses)) {
            throw new Exception("Statut non autorisé.");
        }

        $sql = "";
        $params = [];

        // On construit la requête SQL en fonction du statut
        if ($newStatus === 'approved') {
            // Met à jour le statut, la date d'approbation et remet les autres dates à NULL
            $sql = "UPDATE utilisateurs SET status = ?, date_approbation = NOW(), date_rejet = NULL, date_blocage = NULL WHERE id = ?";
            $params = [$newStatus, $userId];
        } 
        elseif ($newStatus === 'rejected') {
            // Met à jour le statut, la date de rejet et remet les autres à NULL
            $sql = "UPDATE utilisateurs SET status = ?, date_rejet = NOW(), date_approbation = NULL, date_blocage = NULL WHERE id = ?";
            $params = [$newStatus, $userId];
        } 
        elseif ($newStatus === 'blocked') {
            // LA CORRECTION EST ICI : On met à jour le statut ET la date de blocage
            $sql = "UPDATE utilisateurs SET status = ?, date_blocage = NOW() WHERE id = ?";
            $params = [$newStatus, $userId];
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        echo json_encode(['success' => true, 'message' => 'Statut mis à jour.']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log($e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Une erreur serveur est survenue.']);
}
?>