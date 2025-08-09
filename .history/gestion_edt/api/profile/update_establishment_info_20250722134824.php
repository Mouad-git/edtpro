<?php
require_once '../auth/session_check.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$etablissement_id = $_SESSION['etablissement_id'];

try {
    $pdo->beginTransaction();
    
    // Mettre à jour les espaces (méthode DELETE + INSERT)
    if (isset($data['espaces'])) {
        $pdo->prepare("DELETE FROM espaces WHERE etablissement_id = ?")->execute([$etablissement_id]);
        $stmtEspaces = $pdo->prepare("INSERT INTO espaces (etablissement_id, nom_espace) VALUES (?, ?)");
        foreach ($data['espaces'] as $espace) {
            if (!empty(trim($espace))) {
                $stmtEspaces->execute([$etablissement_id, trim($espace)]);
            }
        }
    }
    
    // Mettre à jour le calendrier (méthode UPSERT)
    if (isset($data['holidays']) || isset($data['vacations'])) {
        $calendarData = [
            'holidays' => $data['holidays'] ?? '',
            'vacations' => $data['vacations'] ?? ''
        ];
        $stmtCalendar = $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'calendrier', ?) ON DUPLICATE KEY UPDATE donnees_json = VALUES(donnees_json)");
        $stmtCalendar->execute([$etablissement_id, json_encode($calendarData)]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Paramètres de l\'établissement mis à jour.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>