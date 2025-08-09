<?php
/**
 * API pour Sauvegarder un Emploi du Temps
 *
 * Ce script reçoit les données d'un emploi du temps pour une semaine donnée
 * et les insère ou les met à jour dans la base de données.
 * Il est sécurisé et ne fonctionne que pour un utilisateur connecté.
 */

// 1. Inclure le gardien de sécurité. Si l'utilisateur n'est pas connecté, le script s'arrête ici.
require_once '../auth/session_check.php';

// 2. Inclure la configuration de la base de données pour obtenir l'objet $pdo.
require_once '../../config/database.php';

// 3. Récupérer les données envoyées par le JavaScript (au format JSON).
// file_get_contents('php://input') lit le corps brut de la requête.
// json_decode(..., true) le convertit en tableau associatif PHP.
$data = json_decode(file_get_contents('php://input'), true);

// 4. Valider les données reçues.
// C'est une bonne pratique de s'assurer que les données attendues sont bien présentes.
if (!isset($data['semaine']) || !isset($data['emploiDuTemps'])) {
    http_response_code(400); // Erreur 400 "Bad Request"
    echo json_encode(['success' => false, 'message' => 'Données manquantes : la semaine ou l\'emploi du temps n\'a pas été fourni.']);
    exit;
}

// 5. Récupérer l'ID de l'établissement depuis la session.
// On peut faire confiance à cette variable car session_check.php a déjà vérifié son existence.
$etablissement_id = $_SESSION['etablissement_id'];

// 6. Préparer et exécuter la requête SQL.
// On utilise un bloc try...catch pour gérer les erreurs de base de données.
try {
    // La requête SQL "UPSERT" :
    // - INSERT ... : Tente d'insérer une nouvelle ligne.
    // - ON DUPLICATE KEY UPDATE ... : Si une ligne existe déjà avec la même clé unique
    //   (dans notre cas, la paire `etablissement_id` et `valeur_semaine`),
    //   alors au lieu de créer une nouvelle ligne, il met à jour la ligne existante.
    //   VALUES(donnees_json) signifie "utilise la valeur que j'essayais d'insérer".
    $sql = "INSERT INTO emplois_du_temps (etablissement_id, valeur_semaine, donnees_json) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE donnees_json = VALUES(donnees_json), derniere_modification = CURRENT_TIMESTAMP";

    // Préparation de la requête pour éviter les injections SQL.
    $stmt = $pdo->prepare($sql);

    // Exécution de la requête en liant les paramètres.
    // On doit ré-encoder le tableau `emploiDuTemps` en chaîne de caractères JSON pour le stocker.
    $stmt->execute([
        $etablissement_id,
        $data['semaine'],
        json_encode($data['emploiDuTemps'])
    ]);

    // 7. Envoyer une réponse de succès au frontend.
    http_response_code(200); // OK
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    // Si une erreur survient dans le bloc 'try' (ex: problème de connexion, requête invalide),
    // on l'attrape ici.
    http_response_code(500); // Erreur 500 "Internal Server Error"
    // On renvoie un message d'erreur pour aider au débogage.
    echo json_encode(['success' => false, 'message' => "Erreur de base de données: " . $e->getMessage()]);
}
?>