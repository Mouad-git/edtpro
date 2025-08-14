<?php
/**
 * API pour Récupérer Toutes les Données du Profil
 * (Description inchangée)
 */

require_once '../auth/session_check.php';
require_once '../../config/database.php';

header('Content-Type: application/json');

$utilisateur_id = $_SESSION['utilisateur_id'];
$etablissement_id = $_SESSION['etablissement_id'];

try {
    $data = [];

    // --- 1. Récupérer les informations de l'Utilisateur et de l'Établissement (inchangé) ---
    $stmtUser = $pdo->prepare(
        "SELECT u.nom_complet, u.email, e.nom_etablissement, e.complexe
         FROM utilisateurs u
         JOIN etablissements e ON u.id = e.utilisateur_id
         WHERE u.id = ? AND e.id = ?"
    );
    $stmtUser->execute([$utilisateur_id, $etablissement_id]);
    $data['user'] = $stmtUser->fetch(PDO::FETCH_ASSOC);

    // --- 2. Récupérer les détails des Formateurs ---
    // On lit dans la table `formateurs_details` pour obtenir nom, matricule, email et masse horaire
    $stmtFormateurs = $pdo->prepare(
        "SELECT nom_formateur AS nom, matricule, email, masse_horaire_statutaire AS masse_horaire
         FROM formateurs_details
         WHERE etablissement_id = ?
         ORDER BY nom_formateur ASC"
    );
    $stmtFormateurs->execute([$etablissement_id]);
    $formateurs = $stmtFormateurs->fetchAll(PDO::FETCH_ASSOC);

    // Fallback: si la table détaillée est vide, essayer `donnees_de_base` (liste de noms)
    if (!$formateurs || count($formateurs) === 0) {
        $stmtFallback = $pdo->prepare(
            "SELECT donnees_json FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'formateur'"
        );
        $stmtFallback->execute([$etablissement_id]);
        $namesJson = $stmtFallback->fetchColumn();
        $names = $namesJson ? json_decode($namesJson, true) : [];
        if (is_array($names)) {
            $formateurs = array_map(function ($n) {
                return [
                    'nom' => is_string($n) ? $n : '',
                    'matricule' => '',
                    'email' => '',
                    'masse_horaire' => 0,
                ];
            }, $names);
        }
    }

    $data['formateurs'] = $formateurs;

    // --- 3. Récupérer les Espaces (inchangé) ---
    $stmtEspaces = $pdo->prepare("SELECT nom_espace FROM espaces WHERE etablissement_id = ? ORDER BY nom_espace ASC");
    $stmtEspaces->execute([$etablissement_id]);
    $data['espaces'] = $stmtEspaces->fetchAll(PDO::FETCH_COLUMN); 
    
    // --- 4. Récupérer les données du Calendrier (inchangé) ---
    $stmtCalendar = $pdo->prepare("SELECT donnees_json FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'calendrier'");
    $stmtCalendar->execute([$etablissement_id]);
    $calendarJson = $stmtCalendar->fetchColumn();
    $data['calendar'] = $calendarJson ? json_decode($calendarJson, true) : ['holidays' => '', 'vacations' => ''];
    
    // --- 5. Infos des Stages (inchangé) ---
    $stmtStages = $pdo->prepare("SELECT id, groupe_nom, date_debut, date_fin FROM stages WHERE etablissement_id = ? ORDER BY date_debut");
    $stmtStages->execute([$etablissement_id]);
    $data['stages'] = $stmtStages->fetchAll(PDO::FETCH_ASSOC);

    // --- 6. RÉCUPÉRATION DES GROUPES (LE BLOC MANQUANT) ---
    $stmtGroupes = $pdo->prepare("SELECT donnees_json FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'groupe'");
    $stmtGroupes->execute([$etablissement_id]);
    $groupesJson = $stmtGroupes->fetchColumn();
    // On renvoie un simple tableau de noms de groupes
    $data['groupes'] = $groupesJson ? json_decode($groupesJson, true) : [];
    // --- FIN DU BLOC AJOUTÉ ---

    // --- 7. RÉCUPÉRATION DES MODES DES GROUPES ---
    $stmtGroupModes = $pdo->prepare("SELECT donnees_json FROM donnees_de_base WHERE etablissement_id = ? AND type_donnee = 'groupe_mode'");
    $stmtGroupModes->execute([$etablissement_id]);
    $groupModesJson = $stmtGroupModes->fetchColumn();
    $groupModes = $groupModesJson ? json_decode($groupModesJson, true) : [];

    // Fallback si pas encore stocké: déduire depuis `donnees_avancement` (une seule ligne par établissement)
    if (!$groupModes || (is_array($groupModes) && count($groupModes) === 0)) {
        $stmtAva = $pdo->prepare("SELECT donnees_json FROM donnees_avancement WHERE etablissement_id = ?");
        $stmtAva->execute([$etablissement_id]);
        $avaJson = $stmtAva->fetchColumn();
        if ($avaJson) {
            $rows = json_decode($avaJson, true);
            if (is_array($rows)) {
                $modeIdx = 15; // Colonne P
                $groupeIdx = 8; // Colonne I
                foreach ($rows as $r) {
                    $groupeName = trim((string)($r[$groupeIdx] ?? ''));
                    if ($groupeName === '') continue;
                    $modeRaw = trim((string)($r[$modeIdx] ?? ''));
                    if ($modeRaw === '') continue;
                    $upper = mb_strtoupper($modeRaw);
                    $mode = (strpos($upper, 'ALT') !== false) ? 'Alterné' : 'Résidentiel';
                    if (!isset($groupModes[$groupeName])) {
                        $groupModes[$groupeName] = $mode;
                    }
                }
            }
        }
    }

    // Normaliser les clés: TRIM + UPPERCASE pour correspondre aux noms de groupes affichés
    $normalizedModes = [];
    if (is_array($groupModes)) {
        foreach ($groupModes as $groupName => $modeVal) {
            $normalizedKey = strtoupper(trim((string)$groupName));
            if ($normalizedKey !== '') {
                $normalizedModes[$normalizedKey] = $modeVal;
            }
        }
    }
    $data['groupe_modes'] = $normalizedModes;

    // Si tout s'est bien passé, on envoie la réponse complète.
    echo json_encode(['success' => true, 'data' => $data]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur du serveur lors de la récupération des données du profil: ' . $e->getMessage()]);
}
?>

