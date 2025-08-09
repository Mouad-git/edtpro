<?php
// api/admin/get_all_users.php
ini_set('display_errors', 1); // Gardez ceci pour le débogage
error_reporting(E_ALL);

require_once '../auth/session_check_admin.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    // On sélectionne TOUTES les colonnes nécessaires, y compris les dates
    $stmt = $pdo->query(
        "SELECT u.id, u.nom_complet, u.email, u.status, u.date_inscription, u.date_approbation, u.date_rejet, e.nom_etablissement 
         FROM utilisateurs u 
         LEFT JOIN etablissements e ON u.id = e.utilisateur_id 
         WHERE u.role = 'director'
         GROUP BY u.id"
    );
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $users]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>