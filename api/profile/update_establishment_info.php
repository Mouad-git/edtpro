<?php
// api/profile/update_establishment_info.php
require_once '../auth/session_check.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$etablissement_id = $_SESSION['etablissement_id'];

// Fonction de validation des données de jour férié
function validateHolidayData($holidayData, $action) {
    $errors = [];
    
    if (empty($holidayData['nom'])) {
        $errors[] = 'Le nom du jour férié est requis';
    }
    
    if (empty($holidayData['date'])) {
        $errors[] = 'La date est requise';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $holidayData['date'])) {
        $errors[] = 'Format de date invalide (YYYY-MM-DD attendu)';
    } elseif (!strtotime($holidayData['date'])) {
        $errors[] = 'Date invalide';
    }
    
    if (isset($holidayData['type']) && !in_array($holidayData['type'], ['civil', 'religious', 'custom'])) {
        $errors[] = 'Type de jour férié invalide';
    }
    
    return $errors;
}

// Fonction pour normaliser les données de jour férié
function normalizeHolidayData($holidayData) {
    return [
        'id' => $holidayData['id'] ?? uniqid('holiday_'),
        'nom' => trim($holidayData['nom']),
        'date' => $holidayData['date'],
        'type' => $holidayData['type'] ?? 'custom',
        'description' => trim($holidayData['description'] ?? ''),
        'created_at' => $holidayData['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
}

try {
    $pdo->beginTransaction();
    
    // Gestion CRUD des jours fériés individuels
    if (isset($data['holiday_action']) && isset($data['holiday_data'])) {
        $action = $data['holiday_action'];
        $holidayData = $data['holiday_data'];
        
        // Validation des données
        $validationErrors = validateHolidayData($holidayData, $action);
        if (!empty($validationErrors)) {
            throw new Exception('Données invalides: ' . implode(', ', $validationErrors));
        }
        
        // Récupérer les jours fériés existants
        $stmt = $pdo->prepare("SELECT jours_feries FROM calendrier WHERE etablissement_id = ?");
        $stmt->execute([$etablissement_id]);
        $result = $stmt->fetch();
        
        $existingHolidays = [];
        if ($result && !empty($result['jours_feries'])) {
            $decoded = json_decode($result['jours_feries'], true);
            $existingHolidays = is_array($decoded) ? $decoded : [];
        }
        
        switch ($action) {
            case 'add':
                // Vérifier les doublons de date
                $duplicateDate = array_filter($existingHolidays, function($h) use ($holidayData) {
                    return $h['date'] === $holidayData['date'];
                });
                
                if (!empty($duplicateDate)) {
                    throw new Exception('Un jour férié existe déjà à cette date');
                }
                
                $newHoliday = normalizeHolidayData($holidayData);
                $existingHolidays[] = $newHoliday;
                break;
                
            case 'edit':
                // Modifier un jour férié existant
                $found = false;
                foreach ($existingHolidays as &$holiday) {
                    if (($holidayData['id'] && $holiday['id'] === $holidayData['id']) || 
                        (!$holidayData['id'] && $holiday['date'] === $holidayData['date'])) {
                        
                        // Vérifier les doublons de date si la date a changé
                        if ($holiday['date'] !== $holidayData['date']) {
                            $duplicateDate = array_filter($existingHolidays, function($h) use ($holidayData, $holiday) {
                                return $h['date'] === $holidayData['date'] && $h['id'] !== $holiday['id'];
                            });
                            
                            if (!empty($duplicateDate)) {
                                throw new Exception('Un jour férié existe déjà à cette nouvelle date');
                            }
                        }
                        
                        // Préserver certaines métadonnées
                        $holidayData['id'] = $holiday['id'];
                        $holidayData['created_at'] = $holiday['created_at'] ?? date('Y-m-d H:i:s');
                        $holiday = normalizeHolidayData($holidayData);
                        $found = true;
                        break;
                    }
                }
                
                if (!$found) {
                    throw new Exception('Jour férié introuvable');
                }
                break;
                
            case 'delete':
                // Supprimer un jour férié
                $initialCount = count($existingHolidays);
                $existingHolidays = array_filter($existingHolidays, function($holiday) use ($holidayData) {
                    return !(($holidayData['id'] && $holiday['id'] === $holidayData['id']) || 
                             (!$holidayData['id'] && $holiday['date'] === $holidayData['date']));
                });
                
                if (count($existingHolidays) === $initialCount) {
                    throw new Exception('Jour férié introuvable pour suppression');
                }
                
                // Réindexer le tableau
                $existingHolidays = array_values($existingHolidays);
                break;
                
            case 'bulk_add':
                // Ajout en lot
                if (!isset($holidayData['holidays']) || !is_array($holidayData['holidays'])) {
                    throw new Exception('Données d\'import invalides');
                }
                
                $addedCount = 0;
                $skippedCount = 0;
                
                foreach ($holidayData['holidays'] as $holiday) {
                    $errors = validateHolidayData($holiday, 'add');
                    if (!empty($errors)) {
                        $skippedCount++;
                        continue;
                    }
                    
                    // Vérifier les doublons
                    $duplicate = array_filter($existingHolidays, function($h) use ($holiday) {
                        return $h['date'] === $holiday['date'];
                    });
                    
                    if (!empty($duplicate)) {
                        $skippedCount++;
                        continue;
                    }
                    
                    $existingHolidays[] = normalizeHolidayData($holiday);
                    $addedCount++;
                }
                
                $message = "Import terminé: {$addedCount} jour(s) férié(s) ajouté(s)";
                if ($skippedCount > 0) {
                    $message .= ", {$skippedCount} ignoré(s) (doublons ou erreurs)";
                }
                break;
                
            default:
                throw new Exception('Action non reconnue: ' . $action);
        }
        
        // Trier les jours fériés par date
        usort($existingHolidays, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        // Sauvegarder les jours fériés mis à jour
        $stmtUpdate = $pdo->prepare(
            "INSERT INTO calendrier (etablissement_id, jours_feries) 
             VALUES (?, ?) 
             ON DUPLICATE KEY UPDATE jours_feries = VALUES(jours_feries)"
        );
        $stmtUpdate->execute([$etablissement_id, json_encode($existingHolidays)]);
        
        $pdo->commit();
        
        $actionMessages = [
            'add' => 'Jour férié ajouté avec succès.',
            'edit' => 'Jour férié modifié avec succès.',
            'delete' => 'Jour férié supprimé avec succès.',
            'bulk_add' => $message ?? 'Import en lot effectué avec succès.'
        ];
        
        echo json_encode([
            'success' => true, 
            'message' => $actionMessages[$action],
            'holidays' => $existingHolidays,
            'count' => count($existingHolidays)
        ]);
        return;
    }
    
    // Gestion originale des espaces et calendrier (vacances)
    // Mettre à jour les espaces (méthode DELETE + INSERT)
    if (isset($data['espaces'])) {
        $pdo->prepare("DELETE FROM espaces WHERE etablissement_id = ?")->execute([$etablissement_id]);
        $stmtEspaces = $pdo->prepare("INSERT INTO espaces (etablissement_id, nom_espace) VALUES (?, ?)");
        foreach ($data['espaces'] as $espace) {
            if (!empty(trim($espace))) {
                $stmtEspaces->execute([$etablissement_id, trim($espace)]);
            }
        }
    }
    
    // Mettre à jour le calendrier (méthode UPSERT pour les vacances)
    if (isset($data['holidays']) || isset($data['vacations'])) {
        // Convertir les données du format frontend vers le format de la base de données
        $holidays = [];
        $vacations = [];
        
        // Traiter les jours fériés
        if (!empty($data['holidays'])) {
            $holidaysData = json_decode($data['holidays'], true);
            if (is_array($holidaysData)) {
                $holidays = $holidaysData;
            }
        }
        
        // Traiter les vacances (format: "DD/MM/YYYY - DD/MM/YYYY")
        if (!empty($data['vacations'])) {
            // Utiliser preg_split pour gérer tous les types de sauts de ligne
            $vacationLines = preg_split('/\r\n|\r|\n/', $data['vacations']);
            
            foreach ($vacationLines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // Parser le format "DD/MM/YYYY - DD/MM/YYYY"
                if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s*-\s*(\d{2}\/\d{2}\/\d{4})/', $line, $matches)) {
                    $vacations[] = [
                        'nom' => 'Vacances',
                        'debut' => $matches[1],
                        'fin' => $matches[2]
                    ];
                }
            }
        }
        
        $stmtCalendar = $pdo->prepare("INSERT INTO calendrier (etablissement_id, jours_feries, vacances) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE jours_feries = VALUES(jours_feries), vacances = VALUES(vacances)");
        $stmtCalendar->execute([$etablissement_id, json_encode($holidays), json_encode($vacations)]);
    }

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Paramètres de l\'établissement mis à jour.']);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>