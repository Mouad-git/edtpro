<?php
/**
 * API pour la Demande de Réinitialisation de Mot de Passe
 *
 * Ce script reçoit une adresse e-mail.
 * 1. Valide l'e-mail.
 * 2. Cherche si un utilisateur VÉRIFIÉ existe avec cet e-mail.
 * 3. Si oui :
 *    a) Génère un jeton (token) unique et sécurisé.
 *    b) Hashe ce jeton avant de le stocker en base de données.
 *    c) Enregistre le jeton haché avec une date d'expiration.
 *    d) Envoie un e-mail à l'utilisateur contenant un lien avec le jeton EN CLAIR.
 * 4. Pour des raisons de sécurité, il renvoie TOUJOURS une réponse positive, 
 *    que l'e-mail existe ou non, pour empêcher l'énumération d'utilisateurs.
 */

// On inclut les fichiers nécessaires
require_once '../../config/database.php';   // Pour la connexion à la BDD ($pdo)
require_once '../../vendor/autoload.php'; // Pour charger PHPMailer

// On importe les classes de PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// On indique que la réponse sera au format JSON.
header('Content-Type: application/json');

// Récupère les données JSON envoyées par le frontend.
$data = json_decode(file_get_contents("php://input"));

// --- Mesure de sécurité : Message de réponse générique ---
// On prépare ce message pour le renvoyer dans tous les cas.
// Cela empêche un acteur malveillant de savoir si un email est enregistré dans notre système ou non.
$genericResponseMessage = 'Si un compte correspondant à cet e-mail existe, un lien de réinitialisation a été envoyé.';

// --- Étape 1 : Validation de l'e-mail ---
if (!isset($data->email) || !filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
    // Même si l'email est invalide, on renvoie le message générique pour ne donner aucune information.
    echo json_encode(['success' => true, 'message' => $genericResponseMessage]);
    exit;
}

try {
    // --- Étape 2 : Chercher un utilisateur ACTIF (vérifié) ---
    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ? AND is_verified = TRUE");
    $stmt->execute([$data->email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // --- Étape 3 : Si un utilisateur est trouvé, on procède ---
    if ($user) {
        // a) Générer un jeton unique et cryptographiquement sécurisé.
        // random_bytes(32) crée 32 octets aléatoires.
        // bin2hex() les convertit en une chaîne de 64 caractères hexadécimaux.
        $token = bin2hex(random_bytes(32));
        
        // b) Hasher le jeton avant de le stocker dans la base de données.
        // Si la BDD est compromise, les jetons ne peuvent pas être utilisés directement.
        $token_hash = hash('sha256', $token);
        
        $pdo->beginTransaction();

        // c) Supprimer les anciens jetons pour cet utilisateur pour invalider les vieilles demandes.
        $pdo->prepare("DELETE FROM password_resets WHERE utilisateur_id = ?")->execute([$user['id']]);
        
        // d) Insérer le nouveau jeton haché avec une expiration d'une heure.
        $stmt = $pdo->prepare("INSERT INTO password_resets (utilisateur_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        $stmt->execute([$user['id'], $token_hash]);
        
        $pdo->commit();

        // e) Construire le lien de réinitialisation complet.
        // Le jeton est ajouté EN CLAIR dans l'URL. C'est normal.
        $resetLink = "http://localhost/gestion_edt/public/reset_password.html?token=" . $token;

        // f) Envoyer l'e-mail.
        $mail = new PHPMailer(true);
        // Configuration du serveur SMTP (Gmail)
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'edtproservice@gmail.com';     // <<== REMPLACEZ PAR VOTRE EMAIL GMAIL
        $mail->Password   = 'pgst zzqh wlpo evuw';        // <<== REMPLACEZ PAR VOTRE MOT DE PASSE D'APPLICATION
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        // Destinataires
        $mail->setFrom('votre.adresse.email@gmail.com', 'Gestionnaire EDT');
        $mail->addAddress($data->email);

        // Contenu
        $mail->isHTML(true);
        $mail->Subject = 'Réinitialisation de votre mot de passe';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif;'>
                <h3>Demande de Réinitialisation de Mot de Passe</h3>
                <p>Bonjour,</p>
                <p>Vous recevez cet e-mail car une demande de réinitialisation de mot de passe a été effectuée pour votre compte.</p>
                <p>Veuillez cliquer sur le lien ci-dessous pour définir un nouveau mot de passe :</p>
                <p style='margin: 20px 0;'>
                    <a href='{$resetLink}' style='background-color: #000; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        Réinitialiser mon mot de passe
                    </a>
                </p>
                <p>Ce lien expirera dans une heure.</p>
                <p>Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet e-mail en toute sécurité.</p>
                <br>
                <p>Cordialement,<br>L'équipe du Gestionnaire EDT</p>
            </div>";
        $mail->AltBody = "Pour réinitialiser votre mot de passe, veuillez visiter le lien suivant : {$resetLink}";

        $mail->send();
    }
    // Si aucun utilisateur n'a été trouvé ($user est false), on ne fait rien.
    // On ne veut pas révéler que l'email n'existe pas.

} catch (Exception $e) {
    // En cas d'erreur (BDD, envoi d'email), on annule la transaction si elle était en cours.
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // On ne renvoie pas l'erreur à l'utilisateur pour des raisons de sécurité.
    // On peut la logger dans un fichier sur le serveur pour le débogage.
    // error_log("Erreur dans request_reset.php: " . $e->getMessage());
}

// --- Étape 4 : Toujours renvoyer le message de succès générique ---
echo json_encode(['success' => true, 'message' => $genericResponseMessage]);
?>