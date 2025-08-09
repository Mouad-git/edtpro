<?php
require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));

// ... Validation des données $data->email et $data->code ...

// On cherche l'utilisateur et on récupère son ID
$stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
$stmt->execute([$data->email]);
$user = $stmt->fetch();

if (!$user) { /* ... Gérer erreur ... */ }

// On cherche le code dans la table de vérification
$stmt = $pdo->prepare("SELECT * FROM verification_codes WHERE utilisateur_id = ? AND code = ? AND expires_at > NOW()");
$stmt->execute([$user['id'], $data->code]);
$verification = $stmt->fetch();

if ($verification) {
    // Le code est bon !
    $pdo->beginTransaction();
    try {
        // On active le compte utilisateur
        $stmt = $pdo->prepare("UPDATE utilisateurs SET is_verified = TRUE WHERE id = ?");
        $stmt->execute([$user['id']]);

        // On supprime tous les codes de vérification pour cet utilisateur
        $stmt = $pdo->prepare("DELETE FROM verification_codes WHERE utilisateur_id = ?");
        $stmt->execute([$user['id']]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Compte vérifié avec succès !']);
    } catch (Exception $e) {
        $pdo->rollBack();
        // ... Gérer erreur ...
    }
} else {
    // Le code est incorrect ou a expiré
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Code invalide ou expiré.']);
}
?>