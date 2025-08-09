<?php
// api/admin/get_all_users.php
require_once '../auth/session_check_admin.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

try {
    // On récupère tous les utilisateurs qui ont le rôle 'director'
    // avec les informations de leur premier établissement (s'ils en ont plusieurs)
    $stmt = $pdo->query(
        "SELECT u.id, u.nom_complet, u.email, u.status, e.nom_etablissement 
         FROM utilisateurs u 
         LEFT JOIN etablissements e ON u.id = e.utilisateur_id 
         WHERE u.role = 'director'
         GROUP BY u.id" // Pour s'assurer qu'on n'a qu'une ligne par directeur
    );
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $users]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>