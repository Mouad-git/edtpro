<?php
/**
 * API pour Récupérer Toutes les Données du Profil
 *
 * Ce script est appelé une seule fois au chargement de la page profile.html.
 * Il rassemble les informations depuis plusieurs tables pour construire une réponse JSON complète
 * qui permettra de peupler tous les panneaux de la page de profil.
 *
 * Données récupérées :
 * - Depuis 'utilisateurs' et 'etablissements': Nom, email, nom de l'établissement, complexe.
 * - Depuis 'formateurs_details': La liste complète des formateurs avec leurs détails personnalisés.
 * - Depuis 'espaces': La liste des salles/espaces de l'établissement.
 * - Depuis 'calendrier': Les jours fériés et les périodes de vacances.
 */

// On inclut le gardien strict. L'utilisateur doit être pleinement authentifié pour voir son profil.
require_once '../auth/session_check.php';
// On inclut la configuration de la base de données pour obtenir l'objet $pdo.
require_once '../../config/database.php';

// On indique que la réponse sera au format JSON.
header('Content-Type: application/json');

// On récupère les ID depuis la session (sécurisé).
$utilisateur_id = $_SESSION['utilisateur_id'];
$etablissement_id = $_SESSION['etablissement_id'];

try {
    // On initialise le tableau qui contiendra toutes nos données.
    $data = [];

    // --- 1. Récupérer les informations de l'Utilisateur et de l'Établissement ---
    // On utilise une jointure (JOIN) pour récupérer les données des deux tables en une seule requête.
    $stmtUser = $pdo->prepare(
        "SELECT u.nom_complet, u.email, e.nom_etablissement, e.complexe
         FROM utilisateurs u
         JOIN etablissements e ON u.id = e.utilisateur_id
         WHERE u.id = ? AND e.id = ?"
    );
    $stmtUser->execute([$utilisateur_id, $etablissement_id]);
    $data['user'] = $stmtUser->fetch(PDO::FETCH_ASSOC);

    // --- 2. Récupérer les détails des Formateurs (depuis la table stable) ---
    // On renomme les colonnes avec 'AS' pour correspondre à ce que le JavaScript attend.
    $stmtFormateurs = $pdo->prepare(
        "SELECT nom_formateur as nom, matricule, email, masse_horaire_statutaire as masse_horaire 
         FROM formateurs_details 
         WHERE etablissement_id = ? 
         ORDER BY nom_formateur ASC"
    );
    $stmtFormateurs->execute([$etablissement_id]);
    $data['formateurs'] = $stmtFormateurs->fetchAll(PDO::FETCH_ASSOC);

    // --- 3. Récupérer les Espaces ---
    $stmtEspaces = $pdo->prepare("SELECT nom_espace FROM espaces WHERE etablissement_id = ? ORDER BY nom_espace ASC");
    $stmtEspaces->execute([$etablissement_id]);
    // fetchAll(PDO::FETCH_COLUMN) renvoie un simple tableau de chaînes de caractères (ex: ["Salle 1", "TEAMS"]).
    $data['espaces'] = $stmtEspaces->fetchAll(PDO::FETCH_COLUMN); 
    
    // --- 4. Récupérer les données du Calendrier (depuis la table stable) ---
    $stmtCalendar = $pdo->prepare("SELECT jours_feries, vacances FROM calendrier WHERE etablissement_id = ?");
    $stmtCalendar->execute([$etablissement_id]);
    $calendarRaw = $stmtCalendar->fetch(PDO::FETCH_ASSOC);
    
    // On décode les chaînes JSON en tableaux PHP. Si rien n'est trouvé, on renvoie des tableaux vides.
    $data['calendar'] = [
        'holidays' => $calendarRaw && $calendarRaw['jours_feries'] ? json_decode($calendarRaw['jours_feries']) : [],
        'vacations' => $calendarRaw && $calendarRaw['vacances'] ? json_decode($calendarRaw['vacances']) : []
    ];

    

    // Si tout s'est bien passé, on envoie la réponse complète.
    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    // En cas d'erreur de base de données, on renvoie une erreur 500.
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur du serveur lors de la récupération des données du profil: ' . $e->getMessage()]);
}
?>