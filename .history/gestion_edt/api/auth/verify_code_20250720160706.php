<?php
session_start();
require_once '../../config/database.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"));

// --- Validation des données d'entrée ---
if (
    !isset($data->email, $data->code) ||
    !filter_var($data->email, FILTER_VALIDATE_EMAIL) ||
    empty(trim((string) $data->code)) ||
    !is_numeric($data->code) ||
    strlen((string) $data->code) !== 6 // On s'attend à un code à 6 chiffres
) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Veuillez fournir un email valide et un code de vérification à 6 chiffres.']);
    exit;
}

try {
    // --- Retrouver l'utilisateur par email et vérifier son statut ---
    $stmt = $pdo->prepare("SELECT id, is_verified FROM utilisateurs WHERE email = ?");
    $stmt->execute([$data->email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(404); // Not Found
        echo json_encode(['success' => false, 'message' => 'Aucun compte n\'est associé à cet email.']);
        exit;
    }

    // --- Gérer le cas où le compte est déjà vérifié ---
    if ($user['is_verified']) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Ce compte est déjà vérifié. Vous pouvez vous connecter.']);
        exit;
    }

    // --- Vérifier le code dans la table de vérification (validité et expiration) ---
    $stmt = $pdo->prepare("SELECT * FROM verification_codes WHERE utilisateur_id = ? AND code = ? AND expires_at > NOW()");
    $stmt->execute([$user['id'], $data->code]);
    $verification = $stmt->fetch();

    if ($verification) {
        // Le code est bon ! On procède à l'activation.
        $pdo->beginTransaction();
        
        // 1. On active le compte utilisateur
        $stmtUpdate = $pdo->prepare("UPDATE utilisateurs SET is_verified = TRUE WHERE id = ?");
        $stmtUpdate->execute([$user['id']]);

        // 2. On supprime tous les codes de vérification pour cet utilisateur (nettoyage)
        $stmtDelete = $pdo->prepare("DELETE FROM verification_codes WHERE utilisateur_id = ?");
        $stmtDelete->execute([$user['id']]);

        $pdo->commit();

        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Compte vérifié avec succès ! Vous pouvez maintenant vous connecter.']);

    } else {
        // Le code est incorrect ou a expiré
        http_response_code(400); // Bad Request
        echo json_encode(['success' => false, 'message' => 'Code invalide ou expiré.']);
    }

} catch (PDOException $e) {
    // Si une transaction est en cours, on l'annule pour garantir l'intégrité
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500); // Internal Server Error
    error_log("Erreur de vérification de code: " . $e->getMessage()); // Log pour le debug (ne sera pas montré à l'utilisateur)
    echo json_encode(['success' => false, 'message' => 'Une erreur interne est survenue. Veuillez réessayer plus tard.']);
}
?>