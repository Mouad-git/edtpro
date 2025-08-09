<?php
// AJOUTEZ CES LIGNES POUR LE DÉBOGAGE
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// api/auth/register.php
require_once '../../config/security.php';
require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;


// Démarrage de session sécurisé
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Vérification de la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}
/* DÉSACTIVÉ POUR LE DÉVELOPPEMENT
//
// Rate limiting pour l'inscription
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
if (!check_rate_limit("register_$ip", 50, 60)) { // 50 tentatives max par minute
    secure_log("Rate limit dépassé pour l'inscription - IP: $ip", 'WARNING');
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Trop de tentatives d\'inscription. Veuillez réessayer dans 1 heure.']);
    exit;
}
*/
// Récupération et validation des données
$input = file_get_contents("php://input");
$data = json_decode($input);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données JSON invalides']);
    exit;
}

// --- Étape 1 : Validation et Nettoyage des Données ---
$nom_complet = isset($data->nom_complet) ? sanitize_input($data->nom_complet) : '';
$complexe = isset($data->complexe) ? sanitize_input($data->complexe) : '';
$email = isset($data->email) ? sanitize_input($data->email) : '';
$password = isset($data->mot_de_passe) ? $data->mot_de_passe : '';
$nom_etablissement = isset($data->nom_etablissement) ? sanitize_input($data->nom_etablissement) : '';

// Validation complète des données
$errors = [];

if (empty($nom_complet) || strlen($nom_complet) < 2 || strlen($nom_complet) > 100) {
    $errors[] = 'Le nom complet doit contenir entre 2 et 100 caractères';
}

if (empty($complexe) || strlen($complexe) < 2 || strlen($complexe) > 100) {
    $errors[] = 'Le nom du complexe doit contenir entre 2 et 100 caractères';
}

if (!validate_email($email)) {
    $errors[] = 'Adresse email invalide';
}

if (!validate_password($password)) {
    $errors[] = 'Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule et un chiffre';
}

if (empty($nom_etablissement) || strlen($nom_etablissement) < 2 || strlen($nom_etablissement) > 100) {
    $errors[] = 'Le nom de l\'établissement doit contenir entre 2 et 100 caractères';
}

if (!empty($errors)) {
    secure_log("Tentative d'inscription avec données invalides - IP: $ip", 'WARNING');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides: ' . implode(', ', $errors)]);
    exit;
}

try {
    // --- Étape 2 : Vérification de l'Utilisateur Existant (AVANT la transaction) ---
    $existingUser = secure_fetch_one($pdo, "SELECT id, is_verified FROM utilisateurs WHERE email = ?", [$email]);

    if ($existingUser && $existingUser['is_verified']) {
        secure_log("Tentative d'inscription avec email déjà vérifié: $email - IP: $ip", 'WARNING');
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cette adresse email est déjà utilisée par un compte actif.']);
        exit;
    }

    // --- Étape 3 : Opérations d'Écriture dans une Transaction ---
    $pdo->beginTransaction();

    if ($existingUser) {
        $utilisateur_id = $existingUser['id'];
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        secure_update($pdo, 'utilisateurs', 
            ['nom_complet' => $nom_complet, 'mot_de_passe' => $hashed_password], 
            'id = ?', [$utilisateur_id]
        );
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $utilisateur_id = secure_insert($pdo, 'utilisateurs', [
            'nom_complet' => $nom_complet,
            'email' => $email,
            'mot_de_passe' => $hashed_password
        ]);

        $annee_scolaire_par_defaut = date('Y') . '-' . (date('Y') + 1);
        secure_insert($pdo, 'etablissements', [
            'utilisateur_id' => $utilisateur_id,
            'complexe' => $complexe,
            'nom_etablissement' => $nom_etablissement,
            'annee_scolaire' => $annee_scolaire_par_defaut
        ]);
    }

    // Suppression des anciens codes de vérification
    secure_delete($pdo, 'verification_codes', 'utilisateur_id = ?', [$utilisateur_id]);
    
    // Génération d'un nouveau code de vérification
    $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    secure_insert($pdo, 'verification_codes', [
        'utilisateur_id' => $utilisateur_id,
        'code' => $verification_code,
        'expires_at' => date('Y-m-d H:i:s', time() + 900) // 15 minutes
    ]);

    // --- Étape 4 : Envoi de l'e-mail sécurisé ---
    $mail = new PHPMailer(true);
    
    // Configuration du serveur SMTP sécurisé
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'] ?? 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USER'] ?? 'edtproservice@gmail.com';
    $mail->Password = $_ENV['SMTP_PASS'] ?? 'gvqt gbea qkia gkoo';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->CharSet = 'UTF-8';
    
    // Configuration de sécurité supplémentaire
    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ];
    
    // Destinataires et contenu
    $mail->setFrom($_ENV['SMTP_USER'] ?? 'edtproservice@gmail.com', 'Service EDTpro');
    $mail->addAddress($email, $nom_complet);
    $mail->isHTML(true);
    $mail->Subject = 'Votre code de vérification pour EDTpro';
    
    // Template HTML sécurisé
    $mail->Body = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Code de vérification EDTpro</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #2c3e50;">Vérification de votre compte EDTpro</h2>
            <p>Bonjour <strong>{$nom_complet}</strong>,</p>
            <p>Votre code de vérification est :</p>
            <div style="background-color: #f8f9fa; padding: 15px; text-align: center; border-radius: 5px; margin: 20px 0;">
                <h1 style="color: #007bff; font-size: 32px; margin: 0; letter-spacing: 5px;">{$verification_code}</h1>
            </div>
            <p>Ce code expire dans 15 minutes.</p>
            <p>Si vous n'avez pas demandé cette inscription, ignorez cet email.</p>
            <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
            <p style="font-size: 12px; color: #666;">Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
        </div>
    </body>
    </html>
HTML;
    
    $mail->AltBody = "Votre code de vérification EDTpro est : {$verification_code}";

    $mail->send();

    // Si on arrive ici, l'e-mail est parti. On peut valider la transaction.
    $pdo->commit();
    
    secure_log("Inscription réussie pour l'email: $email - IP: $ip", 'INFO');
    
    echo json_encode([
        'success' => true, 
        'action' => 'verify',
        'email' => $email
    ]);

} catch (PDOException $e) {
    // Erreur spécifique à la base de données
    if ($pdo->inTransaction()) $pdo->rollBack();
    secure_log("Erreur BDD lors de l'inscription: " . $e->getMessage() . " - IP: $ip", 'ERROR');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données. Veuillez réessayer.']);
} catch (PHPMailerException $e) {
    // Erreur spécifique à l'envoi de l'e-mail
    if ($pdo->inTransaction()) $pdo->rollBack();
    secure_log("Erreur email lors de l'inscription: " . $e->getMessage() . " - IP: $ip", 'ERROR');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email de vérification. Veuillez réessayer.']);
} catch (Exception $e) {
    // Toutes les autres erreurs
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // --- BLOC DE DÉBOGAGE TEMPORAIRE ---
    http_response_code(500);
    // Affichez le message d'erreur réel, le fichier et la ligne
    echo json_encode([
        'success' => false, 
        'message' => 'ERREUR PHP CAPTURÉE : ' . $e->getMessage(),
        'file'    => 'Fichier : ' . $e->getFile(),
        'line'    => 'Ligne : ' . $e->getLine()
    ]);
    // --- FIN DU BLOC DE DÉBOGAGE ---
}
?>