<?php
// api/profile/get_profile_data.php

require_once '../auth/session_check.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

$utilisateur_id = $_SESSION['utilisateur_id'];
$etablissement_id = $_SESSION['etablissement_id'];

try {
    $data = [];

    // 1. Récupérer les informations de l'utilisateur
    $stmtUser = $pdo->prepare("SELECT nom_complet, email FROM utilisateurs WHERE id = ?");
    $stmtUser->execute([$utilisateur_id]);
    $data['user'] = $stmtUser->fetch(PDO::FETCH_ASSOC);

    // 2. Récupérer les espaces de l'établissement
    $stmtEspaces = $pdo->prepare("SELECT nom_espace FROM espaces WHERE etablissement_id = ? ORDER BY nom_espace");
    $stmtEspaces->execute([$etablissement_id]);
    // fetchAll(PDO::FETCH_COLUMN) renvoie un simple tableau de chaînes de caractères
    $data['espaces'] = $stmtEspaces->fetchAll(PDO::FETCH_COLUMN); 
    
    // 3. Récupérer les données du calendrier (jours fériés, vacances)
    $stmtCalendar = $pdo->prepare("SELECT donnees_json FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'calendrier'");
    $stmtCalendar->execute([$etablissement_id]);
    $calendarJson = $stmtCalendar->fetchColumn();
    $data['calendar'] = $calendarJson ? json_decode($calendarJson, true) : ['holidays' => '', 'vacations' => ''];

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>