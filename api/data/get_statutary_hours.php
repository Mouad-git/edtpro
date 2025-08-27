<?php
// api/data/get_statutary_hours.php
require_once '../auth/session_check.php';
require_once '../../config/database.php';
header('Content-Type: application/json');
$etablissement_id = $_SESSION['etablissement_id'];
try {
    $stmt = $pdo->prepare("SELECT nom_formateur, masse_horaire_statutaire FROM formateurs_details WHERE etablissement_id = ? ORDER BY nom_formateur ASC");
    $stmt->execute([$etablissement_id]);
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
