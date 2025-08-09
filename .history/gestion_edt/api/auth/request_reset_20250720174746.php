<?php
// api/auth/request_reset.php
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');
$data = json_decode(file_get_contents("php://input"));

// Pour des raisons de sécurité, on renvoie TOUJOURS un message de succès,
// même si l'email n'existe pas. Cela empêche les gens de deviner quels emails sont inscrits.
$responseMessage = 'Si un compte correspondant à cet e-mail existe, un lien de réinitialisation a été envoyé.';

if (!isset($data->email) || !filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => true, 'message' => $responseMessage]);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND is_verified = TRUE");
$stmt->execute([$data->email]);
$user = $stmt->fetch();

if ($user) {
    try {
        // L'utilisateur existe, on génère un jeton
        $token = bin2hex(random_bytes(32)); // Jeton sécurisé
        $token_hash = hash('sha256', $token); // On hashe le jeton avant de le stocker

        $pdo->beginTransaction();
        // On supprime les anciens jetons pour cet utilisateur
        $pdo->prepare("DELETE FROM password_resets WHERE utilisateur_id = ?")->execute([$user['id']]);
        // On insère le nouveau jeton avec une expiration d'1 heure
        $stmt = $pdo->prepare("INSERT INTO password_resets (utilisateur_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        $stmt->execute([$user['id'], $token_hash]);
        $pdo->commit();

        // On envoie l'e-mail avec le jeton NON HACHÉ
        $resetLink = "http://localhost/gestion_edt/public/reset_password.html?token=" . $token;

        $mail = new PHPMailer(true);
        // ... (votre configuration PHPMailer comme dans register.php) ...
        $mail->Username   = 'edtproservice@gmail.com';
        $mail->Password   = 'pgst zzqh wlpo evuw';
        $mail->setFrom('edtproservice@gmail.com', 'Service EDT');
        $mail->addAddress($data->email);
        $mail->Subject = 'Réinitialisation de votre mot de passe';
        $mail->Body    = "Bonjour,<br><br>Vous avez demandé à réinitialiser votre mot de passe. Veuillez cliquer sur le lien ci-dessous :<br><a href='{$resetLink}'>{$resetLink}</a><br><br>Ce lien expirera dans une heure.<br>Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.";
        $mail->send();

    } catch (Exception $e) {
        // En cas d'erreur (BDD, email), on ne fait rien et on renvoie quand même le message de succès générique.
    }
}

echo json_encode(['success' => true, 'message' => $responseMessage]);
?>