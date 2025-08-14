<?php
// api/profile/get_profile_data.php

require_once '../auth/session_check.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

$utilisateur_id = $_SESSION['utilisateur_id'];
$etablissement_id = $_SESSION['etablissement_id'];

try {
    $data = [];

    // --- 1. Infos Utilisateur & Etablissement (inchangé) ---
    $stmtUser = $pdo->prepare(
        "SELECT u.nom_complet, u.email, e.nom_etablissement, e.complexe
         FROM utilisateurs u
         JOIN etablissements e ON u.id = e.utilisateur_id
         WHERE u.id = ? AND e.id = ?"
    );
    $stmtUser->execute([$utilisateur_id, $etablissement_id]);
    $data['user'] = $stmtUser->fetch(PDO::FETCH_ASSOC);

    // --- 2. Infos Formateurs (inchangé) ---
    $stmtFormateurs = $pdo->prepare("SELECT donnees_json FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'formateur'");
    $stmtFormateurs->execute([$etablissement_id]);
    $formateursJson = $stmtFormateurs->fetchColumn();
    $data['formateurs'] = $formateursJson ? json_decode($formateursJson, true) : [];

    // --- 3. Infos Espaces (inchangé) ---
    $stmtEspaces = $pdo->prepare("SELECT nom_espace FROM espaces WHERE etablissement_id = ? ORDER BY nom_espace ASC");
    $stmtEspaces->execute([$etablissement_id]);
    $data['espaces'] = $stmtEspaces->fetchAll(PDO::FETCH_COLUMN); 
    
    // --- 4. Infos Calendrier (inchangé) ---
    $stmtCalendar = $pdo->prepare("SELECT donnees_json FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'calendrier'");
    $stmtCalendar->execute([$etablissement_id]);
    $calendarJson = $stmtCalendar->fetchColumn();
    $data['calendar'] = $calendarJson ? json_decode($calendarJson, true) : ['holidays' => '', 'vacations' => ''];
    
    // --- 5. Infos des Stages (inchangé) ---
    $stmtStages = $pdo->prepare("SELECT id, groupe_nom, date_debut, date_fin FROM stages WHERE etablissement_id = ? ORDER BY date_debut");
    $stmtStages->execute([$etablissement_id]);
    $data['stages'] = $stmtStages->fetchAll(PDO::FETCH_ASSOC);

    // --- 6. RÉCUPÉRATION ET TRAITEMENT DES GROUPES (CORRIGÉ) ---
    $stmtGroupes = $pdo->prepare("SELECT donnees_json FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'groupe'");
    $stmtGroupes->execute([$etablissement_id]);
    $groupesObjetsJson = $stmtGroupes->fetchColumn();
    $groupesObjets = $groupesObjetsJson ? json_decode($groupesObjetsJson, true) : [];

    // a) Créer la liste simple de noms pour la sélection dans les stages
    $data['groupes'] = is_array($groupesObjets) ? array_column($groupesObjets, 'nom') : [];
    
    // b) Créer la map des modes et classer les groupes
    $groupesClasses = ['Residentiel' => [], 'Alterne' => []];
    if (is_array($groupesObjets)) {
        foreach ($groupesObjets as $groupe) {
            if (isset($groupe['mode']) && $groupe['mode'] === 'Alterné') {
                $groupesClasses['Alterne'][] = $groupe;
            } else {
                $groupesClasses['Residentiel'][] = $groupe;
            }
        }
    }
    $data['groupes_classes'] = $groupesClasses;
    // --- FIN DE LA CORRECTION ---

    // Si tout s'est bien passé, on envoie la réponse complète.
    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur du serveur lors de la récupération des données: ' . $e->getMessage()]);
}
?>