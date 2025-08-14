<?php
// api/admin/update_user_status.php
require_once '../auth/session_check_admin.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"));
$userId = $data->user_id ?? null;
$newStatus = $data->new_status ?? null;

// Validation simple pour s'assurer que les données existent
if (!$userId || !$newStatus) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Données manquantes (user_id ou new_status).']);
    exit;
}

try {
    // CAS 1 : La requête est une suppression définitive
    if ($newStatus === 'deleted') {
        $stmt = $pdo->prepare("DELETE FROM utilisateurs WHERE id = ?");
        $stmt->execute([$userId]);
        
        // rowCount() vérifie si une ligne a bien été affectée
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé avec succès.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé pour la suppression.']);
        }

    // CAS 2 : La requête est une mise à jour de statut
    } else {
        // 1. Créer une "liste blanche" des statuts autorisés pour la sécurité
        $allowed_statuses = ['approved', 'rejected', 'blocked'];
        
        if (!in_array($newStatus, $allowed_statuses)) {
            // Si le statut envoyé n'est pas dans la liste, on refuse la requête
            throw new Exception("Statut non autorisé : " . htmlspecialchars($newStatus));
        }

        // 2. Préparer la requête SQL de base
        $sql = "UPDATE utilisateurs SET status = ?";
        $params = [$newStatus];

        // 3. Ajouter la logique de date (maintenant incluant 'blocked' si vous le souhaitez)
        if ($newStatus === 'approved') {
            $sql .= ", date_approbation = NOW(), date_rejet = NULL, ";
        } elseif ($newStatus === 'rejected') {
            $sql .= ", date_rejet = NOW(), date_approbation = NULL, ";
        } elseif ($newStatus === 'blocked') {
            // Suggestion : Ajoutez une colonne 'date_blocage' à votre table pour tracer cette action
            // Si la colonne existe, décommentez la ligne suivante :
            // $sql .= ", date_blocage = NOW()"; 
        }

        // 4. Finaliser et exécuter la requête
        $sql .= " WHERE id = ?";
        $params[] = $userId;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode(['success' => true, 'message' => 'Statut mis à jour avec succès.']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    // Enregistrez l'erreur dans un fichier de log au lieu de l'afficher à l'utilisateur
    error_log($e->getMessage()); 
    echo json_encode(['success' => false, 'message' => 'Une erreur serveur est survenue.']);
}
?>