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
    // CAS 1: Suppression
    if ($newStatus === 'deleted') {
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
        $stmt->execute([$userId]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé.']);
        } else {
            throw new Exception("L'utilisateur à supprimer n'a pas été trouvé.");
        }
        exit;
    }

    // Liste blanche des statuts de mise à jour
    $allowed_statuses = ['approved', 'rejected', 'blocked'];
    if (!in_array($newStatus, $allowed_statuses)) {
        throw new Exception("Opération non autorisée pour le statut : " . htmlspecialchars($newStatus));
    }
    
    // CAS 2: Mise à jour des statuts
    // On utilise un SWITCH pour une logique claire et séparée
    switch ($newStatus) {
        case 'approved':
            // Remet à zéro les autres dates pour la propreté des données
            $sql = "UPDATE utilisateurs SET status = 'approved', date_approbation = NOW(), date_rejet = NULL, date_blocage = NULL WHERE id = ?";
            break;
        case 'rejected':
            $sql = "UPDATE utilisateurs SET status = 'rejected', date_rejet = NOW(), date_approbation = NULL, date_blocage = NULL WHERE id = ?";
            break;
        case 'blocked':
            // Assurez-vous d'avoir une colonne `date_blocage` dans votre table
            $sql = "UPDATE utilisateurs SET status = 'blocked', date_blocage = NOW() WHERE id = ?";
            break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    
    // LA VÉRIFICATION CRUCIALE : est-ce que la ligne a VRAIMENT été modifiée ?
    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Statut mis à jour avec succès.']);
    } else {
        // Cela se produit si l'utilisateur n'existe pas ou si le statut est déjà le même
        throw new Exception("Aucune mise à jour effectuée. L'utilisateur n'existe pas ou son statut est déjà à jour.");
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log($e->getMessage()); // Pour le débogage côté serveur
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>