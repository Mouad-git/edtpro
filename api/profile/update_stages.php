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
        // Validation basique des formats de date (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $stageData['date_debut']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $stageData['date_fin'])) {
            throw new Exception("Format de date invalide. Utilisez AAAA-MM-JJ.");
        }
        if (strtotime($stageData['date_fin']) < strtotime($stageData['date_debut'])) {
            throw new Exception("La date de fin doit être postérieure ou égale à la date de début.");
        }
        
        $pdo->beginTransaction();
        // Permettre plusieurs stages par groupe (suppression du contrôle de chevauchement)
        $insert = $pdo->prepare("INSERT INTO stages (etablissement_id, groupe_nom, date_debut, date_fin) VALUES (?, ?, ?, ?)");
        foreach ($stageData['groupes'] as $groupe) {
            $groupe = trim((string)$groupe);
            if ($groupe === '') continue;
            $insert->execute([$etablissement_id, $groupe, $stageData['date_debut'], $stageData['date_fin']]);
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