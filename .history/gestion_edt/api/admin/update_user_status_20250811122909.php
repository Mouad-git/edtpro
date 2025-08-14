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
    // Validation basique de l'ID
    if (!validate_id($userId)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Identifiant utilisateur invalide.']);
        exit;
    }

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
            // S'assurer que la colonne `date_blocage` existe (ajout automatique si absente)
            $hasDateBlocage = false;
            try {
                $colCheck = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'utilisateurs' AND COLUMN_NAME = 'date_blocage'");
                $colCheck->execute();
                $hasDateBlocage = ((int)$colCheck->fetchColumn()) > 0;
                if (!$hasDateBlocage) {
                    $pdo->exec("ALTER TABLE utilisateurs ADD COLUMN date_blocage DATETIME NULL");
                    $hasDateBlocage = true; // si l'ALTER réussit
                }
            } catch (Throwable $schemaErr) {
                // Si nous ne pouvons pas vérifier/ajouter la colonne, continuer sans l'utiliser
                $hasDateBlocage = false;
            }
            // Construire la requête en fonction de la présence de la colonne
            if ($hasDateBlocage) {
                $sql = "UPDATE utilisateurs SET status = 'blocked', date_blocage = NOW(), date_approbation = NULL, date_rejet = NULL WHERE id = ?";
            } else {
                $sql = "UPDATE utilisateurs SET status = 'blocked', date_approbation = NULL, date_rejet = NULL WHERE id = ?";
            }
            break;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$userId]);
    
    // Si aucune ligne affectée, vérifier si l'utilisateur existe et si le statut est déjà à jour
    if ($stmt->rowCount() === 0) {
        $checkStmt = $pdo->prepare("SELECT status FROM utilisateurs WHERE id = ? LIMIT 1");
        $checkStmt->execute([$userId]);
        $row = $checkStmt->fetch(PDO::FETCH_ASSOC);
        if ($row && isset($row['status']) && $row['status'] === $newStatus) {
            echo json_encode(['success' => true, 'message' => 'Statut déjà à jour.']);
            exit;
        }
        // Sinon, lever une erreur pertinente
        throw new Exception("Aucune mise à jour effectuée. L'utilisateur n'existe pas ou les données sont inchangées.");
    }

    echo json_encode(['success' => true, 'message' => 'Statut mis à jour avec succès.']);

} catch (Exception $e) {
    http_response_code(500);
    error_log($e->getMessage()); // Pour le débogage côté serveur
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>