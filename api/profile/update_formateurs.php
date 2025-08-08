<?php
// api/profile/update_formateurs.php
require_once '../auth/session_check.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$etablissement_id = $_SESSION['etablissement_id'];

if (!isset($data['formateurs']) || !is_array($data['formateurs'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données des formateurs invalides.']);
    exit;
}

try {
    // On met simplement à jour le bloc JSON complet des formateurs.
    // C'est la méthode la plus simple et la plus fiable.
    $stmt = $pdo->prepare(
        "INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'formateur', ?)
         ON DUPLICATE KEY UPDATE donnees_json = VALUES(donnees_json)"
    );
    $stmt->execute([$etablissement_id, json_encode($data['formateurs'])]);

    echo json_encode(['success' => true, 'message' => 'Informations des formateurs mises à jour.']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>