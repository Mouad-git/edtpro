<?php
// api/data/get_planned_progress.php

require_once '../auth/session_check.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$etablissement_id = $_SESSION['etablissement_id'];
define('SEANCE_DURATION', 2.5); // Durée d'une séance en heures

try {
    // 1. Récupérer les données de base pour obtenir les affectations avec les types
    $stmtBase = $pdo->prepare("SELECT donnees_json FROM donnees_de_base WHERE etablissement_id = ?");
    $stmtBase->execute([$etablissement_id]);
    $baseDataJson = $stmtBase->fetchColumn();
    
    $baseData = null;
    if ($baseDataJson) {
        $baseData = json_decode($baseDataJson, true);
    }
    
    // 2. Récupérer tous les emplois du temps enregistrés pour l'établissement
    $stmt = $pdo->prepare("SELECT donnees_json FROM emplois_du_temps WHERE etablissement_id = ?");
    $stmt->execute([$etablissement_id]);
    $all_timetables_json = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $progressByGroup = [];
    $progressByFormateur = [];
    $progressByModule = []; // Nouveau : suivi par module

    // 3. Parcourir chaque semaine enregistrée
    foreach ($all_timetables_json as $timetable_json) {
        $timetable = json_decode($timetable_json, true);
        
        if (is_array($timetable)) {
            // 4. Agréger les données de cette semaine
            foreach ($timetable as $formateur => $jours) {
                if (!isset($progressByFormateur[$formateur])) {
                    $progressByFormateur[$formateur] = [
                        'total' => 0,
                        'presentiel' => 0,
                        'synchrone' => 0
                    ];
                }
                
                foreach ($jours as $seances) {
                    foreach ($seances as $session) {
                        if (!empty($session['groupe']) && !empty($session['module'])) {
                            $module = $session['module'];
                            $salle = $session['salle'] ?? '';
                            
                            // Déterminer le type basé sur la salle
                            $isSynchrone = (strtoupper($salle) === 'TEAMS');
                            $type = $isSynchrone ? 'synchrone' : 'presentiel';
                            
                            // Si on a les données de base, vérifier la cohérence avec les affectations
                            if ($baseData && isset($baseData['affectations'])) {
                                $matchingAffectation = findMatchingAffectation(
                                    $baseData['affectations'], 
                                    $formateur, 
                                    $session['groupe'], 
                                    $module, 
                                    $type
                                );
                                
                                // Si pas d'affectation correspondante, on utilise quand même le type déterminé par la salle
                                // mais on pourrait ajouter un log ou warning ici
                            }
                            
                            // Ajouter aux heures du formateur
                            $progressByFormateur[$formateur]['total'] += SEANCE_DURATION;
                            $progressByFormateur[$formateur][$type] += SEANCE_DURATION;
                            
                            // Ajouter aux heures du/des groupe(s)
                            $subGroups = preg_split('/\s+/', trim($session['groupe']));
                            foreach ($subGroups as $groupe) {
                                if (!isset($progressByGroup[$groupe])) {
                                    $progressByGroup[$groupe] = [
                                        'total' => 0,
                                        'presentiel' => 0,
                                        'synchrone' => 0
                                    ];
                                }
                                $progressByGroup[$groupe]['total'] += SEANCE_DURATION;
                                $progressByGroup[$groupe][$type] += SEANCE_DURATION;
                            }
                            
                            // Ajouter aux heures du module par groupe
                            foreach ($subGroups as $groupe) {
                                if (!isset($progressByModule[$module])) {
                                    $progressByModule[$module] = [];
                                }
                                if (!isset($progressByModule[$module][$groupe])) {
                                    $progressByModule[$module][$groupe] = [
                                        'total' => 0,
                                        'presentiel' => 0,
                                        'synchrone' => 0
                                    ];
                                }
                                $progressByModule[$module][$groupe]['total'] += SEANCE_DURATION;
                                $progressByModule[$module][$groupe][$type] += SEANCE_DURATION;
                            }
                        }
                    }
                }
            }
        }
    }
    
    // Convertir les structures détaillées en format compatible avec l'ancien système
    $compatibleProgressByGroup = [];
    $compatibleProgressByFormateur = [];
    
    foreach ($progressByGroup as $groupe => $data) {
        $compatibleProgressByGroup[$groupe] = $data['total'];
    }
    
    foreach ($progressByFormateur as $formateur => $data) {
        $compatibleProgressByFormateur[$formateur] = $data['total'];
    }

    // 5. Renvoyer les données agrégées avec les détails par type
    echo json_encode([
        'success' => true,
        'data' => [
            // Format compatible avec l'ancien système
            'progressByGroup' => $compatibleProgressByGroup,
            'progressByFormateur' => $compatibleProgressByFormateur,
            // Nouvelles données détaillées
            'detailedProgressByGroup' => $progressByGroup,
            'detailedProgressByFormateur' => $progressByFormateur,
            'progressByModule' => $progressByModule
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Trouve une affectation correspondante dans les données de base
 */
function findMatchingAffectation($affectations, $formateur, $groupe, $module, $expectedType) {
    foreach ($affectations as $affectation) {
        if ($affectation['formateur'] === $formateur && 
            $affectation['module'] === $module &&
            $affectation['groupe'] === $groupe &&
            isset($affectation['type']) &&
            $affectation['type'] === $expectedType) {
            return $affectation;
        }
    }
    return null;
}
?>