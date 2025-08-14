<?php
// api/profile/update_stages.php
require_once '../auth/session_check.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$etablissement_id = $_SESSION['etablissement_id'];
$action = $data['action'] ?? '';

try {
    if ($action === 'add') {
        $stageData = $data['data'];
        if (empty($stageData['groupes']) || empty($stageData['date_debut']) || empty($stageData['date_fin'])) {
            throw new Exception("Données incomplètes pour l'ajout de stage.");
        }
        
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO stages (etablissement_id, groupe_nom, date_debut, date_fin) VALUES (?, ?, ?, ?)");
        foreach ($stageData['groupes'] as $groupe) {
            $stmt->execute([$etablissement_id, $groupe, $stageData['date_debut'], $stageData['date_fin']]);
        }
        $pdo->commit();
        
        echo json_encode(['success' => true, 'message' => 'Période de stage ajoutée avec succès.']);

    } elseif ($action === 'delete') {
        $stageId = $data['stage_id'] ?? null;
        if (!$stageId) {
            throw new Exception("ID de stage manquant pour la suppression.");
        }

        // Sécurité : On vérifie que le stage appartient bien à l'établissement de l'utilisateur
        $stmt = $pdo->prepare("DELETE FROM stages WHERE id = ? AND etablissement_id = ?");
        $stmt->execute([$stageId, $etablissement_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Stage supprimé avec succès.']);
        } else {
            throw new Exception("Stage non trouvé ou vous n'avez pas la permission de le supprimer.");
        }
    } else {
        throw new Exception("Action non valide.");
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>