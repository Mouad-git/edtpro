<?php
// api/profile/get_profile_data.php

require_once '../auth/session_check.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$utilisateur_id = $_SESSION['utilisateur_id'];
$etablissement_id = $_SESSION['etablissement_id'];

try {
    $data = [];

    // 1. Infos Utilisateur & Etablissement (inchangé)
    $stmtUser = $pdo->prepare(
        "SELECT u.nom_complet, u.email, e.nom_etablissement, e.complexe
         FROM utilisateurs u
         JOIN etablissements e ON u.id = e.utilisateur_id
         WHERE u.id = ? AND e.id = ?"
    );
    $stmtUser->execute([$utilisateur_id, $etablissement_id]);
    $data['user'] = $stmtUser->fetch(PDO::FETCH_ASSOC);

    // 2. Infos Formateurs (inchangé)
    $stmtFormateurs = $pdo->prepare("SELECT donnees_json FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'formateur'");
    $stmtFormateurs->execute([$etablissement_id]);
    $formateursJson = $stmtFormateurs->fetchColumn();
    $data['formateurs'] = $formateursJson ? json_decode($formateursJson, true) : [];

    // --- NOUVEAU BLOC AJOUTÉ ICI ---
    // 3. Infos Groupes
    $stmtGroupes = $pdo->prepare("SELECT donnees_json FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'groupe'");
    $stmtGroupes->execute([$etablissement_id]);
    $groupesJson = $stmtGroupes->fetchColumn();
    $data['groupes'] = $groupesJson ? json_decode($groupesJson, true) : [];
    // --- FIN DU NOUVEAU BLOC ---

    // 4. Infos Espaces (inchangé)
    $stmtEspaces = $pdo->prepare("SELECT nom_espace FROM espaces WHERE etablissement_id = ? ORDER BY nom_espace");
    $stmtEspaces->execute([$etablissement_id]);
    $data['espaces'] = $stmtEspaces->fetchAll(PDO::FETCH_COLUMN); 
    
    // 5. Infos Calendrier (inchangé)
    $stmtCalendar = $pdo->prepare("SELECT donnees_json FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'calendrier'");
    $stmtCalendar->execute([$etablissement_id]);
    $calendarJson = $stmtCalendar->fetchColumn();
    $data['calendar'] = $calendarJson ? json_decode($calendarJson, true) : ['holidays' => '', 'vacations' => ''];

    // 6. Infos des Stages (inchangé)
    $stmtStages = $pdo->prepare("SELECT id, groupe_nom, date_debut, date_fin FROM stages WHERE etablissement_id = ? ORDER BY date_debut");
    $stmtStages->execute([$etablissement_id]);
    $data['stages'] = $stmtStages->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>