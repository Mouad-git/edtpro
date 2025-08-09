<?php
require_once '../../config/security.php'; // Toujours en premier !
/**
 * API pour la Vérification du Code d'Inscription
 *
 * Ce script reçoit un e-mail et un code de vérification.
 * Il effectue les actions suivantes :
 * 1. Valide les données reçues.
 * 2. Cherche l'utilisateur correspondant à l'e-mail.
 * 3. Cherche le code dans la table `verification_codes`, en s'assurant qu'il correspond à l'utilisateur
 *    et qu'il n'a pas expiré.
 * 4. Si le code est valide :
 *    a) Met à jour le statut `is_verified` de l'utilisateur à TRUE.
 *    b) Supprime tous les codes de vérification pour cet utilisateur.
 * 5. Renvoie une réponse de succès ou d'échec au frontend.
 */

// On inclut la configuration de la base de données pour obtenir l'objet $pdo.
require_once '../../config/database.php';

// On indique que la réponse sera au format JSON.
header('Content-Type: application/json');

// Récupère les données JSON envoyées par le frontend.
$data = json_decode(file_get_contents("php://input"));

// --- Étape 1 : Validation des données ---
if (
    !isset($data->email) || !filter_var($data->email, FILTER_VALIDATE_EMAIL) ||
    !isset($data->code) || empty($data->code) || strlen($data->code) !== 6
) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Veuillez fournir un e-mail et un code à 6 chiffres valides.']);
    exit;
}

try {
    // --- Étape 2 : Chercher l'utilisateur par e-mail ---
    $stmtUser = $pdo->prepare("SELECT id, is_verified FROM utilisateurs WHERE email = ?");
    $stmtUser->execute([$data->email]);
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Cas très improbable où l'utilisateur n'existe pas.
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Aucun compte n\'est associé à cet e-mail.']);
        exit;
    }

    if ($user['is_verified']) {
        // Si le compte est déjà vérifié, on informe l'utilisateur et on renvoie un succès.
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Ce compte est déjà vérifié. Vous pouvez vous connecter.']);
        exit;
    }

    // --- Étape 3 : Chercher le code et vérifier sa validité ---
    // On vérifie que le code correspond à l'utilisateur, qu'il est correct,
    // et que la date d'expiration (expires_at) est dans le futur (supérieure à NOW()).
    $stmtCode = $pdo->prepare(
        "SELECT id FROM verification_codes 
         WHERE utilisateur_id = ? AND code = ? AND expires_at > NOW()"
    );
    $stmtCode->execute([$user['id'], $data->code]);
    $verification = $stmtCode->fetch();

    if ($verification) {
        // --- Étape 4 : Le code est valide ! ---
        
        // On utilise une transaction pour s'assurer que les deux opérations (UPDATE et DELETE) réussissent.
        $pdo->beginTransaction();
        
        // a) Activer le compte utilisateur.
        $stmtActivate = $pdo->prepare("UPDATE utilisateurs SET is_verified = TRUE WHERE id = ?");
        $stmtActivate->execute([$user['id']]);

        // b) Supprimer tous les codes de vérification pour cet utilisateur pour nettoyer la base.
        $stmtDelete = $pdo->prepare("DELETE FROM verification_codes WHERE utilisateur_id = ?");
        $stmtDelete->execute([$user['id']]);
        
        $pdo->commit();
        
        // --- Étape 5 : Envoyer une réponse de succès ---
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Compte vérifié avec succès ! Vous pouvez maintenant vous connecter.']);

    } else {
        // Le code est incorrect ou a expiré.
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Le code de vérification est incorrect ou a expiré.']);
    }

} catch (Exception $e) {
    // En cas d'erreur de base de données, on annule la transaction si elle était en cours.
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Erreur du serveur: ' . $e->getMessage()]);
}
?>