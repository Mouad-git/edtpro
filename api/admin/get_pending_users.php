<?php
require_once '../auth/session_check_admin.php'; // Un nouveau gardien strict pour les admins
require_once '../../config/database.php';

$stmt = $pdo->query(
    "SELECT u.id, u.nom_complet, u.email, e.nom_etablissement 
     FROM utilisateurs u 
     JOIN etablissements e ON u.id = e.utilisateur_id 
     WHERE u.status = 'pending'"
);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'data' => $users]);
?>