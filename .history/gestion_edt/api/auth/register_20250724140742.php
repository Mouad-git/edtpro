<?php
/**
 * API pour l'Inscription d'un Nouvel Utilisateur avec Vérification par E-mail
 *
 * Ce script gère le processus d'inscription en plusieurs étapes :
 * 1. Valide les données reçues du formulaire.
 * 2. Vérifie si l'e-mail est déjà utilisé par un compte VÉRIFIÉ.
 * 3. Crée un nouvel utilisateur (ou réutilise un compte non vérifié existant).
 * 4. Génère un code de vérification unique à 6 chiffres.
 * 5. Enregistre ce code dans la base de données avec une date d'expiration.
 * 6. Utilise PHPMailer pour envoyer le code à l'adresse e-mail de l'utilisateur.
 * 7. Renvoie une réponse au frontend pour le rediriger vers la page de vérification.
 */

// On inclut les fichiers nécessaires
require_once '../../config/database.php';   // Pour la connexion à la BDD ($pdo)
require_once '../../vendor/autoload.php'; // Pour charger PHPMailer

// On importe les classes de PHPMailer dans le namespace global
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Récupère les données JSON envoyées par le frontend
$data = json_decode(file_get_contents("php://input"));

// --- Étape 1 : Validation des données ---
if (
    !isset($data->nom_complet) || empty($data->nom_complet) ||
    !isset($data->complexe) || empty($data->complexe) ||
    !isset($data->email) || !filter_var($data->email, FILTER_VALIDATE_EMAIL) ||
    !isset($data->mot_de_passe) || empty($data->mot_de_passe) ||
    !isset($data->nom_etablissement) || empty($data->nom_etablissement)
) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs correctement.']);
    exit;
}

if (strlen($data->mot_de_passe) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères.']);
    exit;
}

// --- Étape 2 : Vérification de l'existence d'un compte VÉRIFIÉ ---
try {
    $stmt = $pdo->prepare("SELECT id, is_verified FROM utilisateurs WHERE email = ?");
    $stmt->execute([$data->email]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser && $existingUser['is_verified']) {
        // Si un utilisateur existe ET qu'il est déjà vérifié, on refuse l'inscription.
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'message' => 'Cette adresse email est déjà utilisée par un compte actif.']);
        exit;
    }

    // --- Étape 3 & 4 & 5 : Création/Mise à jour de l'utilisateur et génération du code ---
    $pdo->beginTransaction();

    if ($existingUser) {
        // Un compte non vérifié existe déjà, on le met à jour (mot de passe, etc.)
        $utilisateur_id = $existingUser['id'];
        $hashed_password = password_hash($data->mot_de_passe, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE utilisateurs SET nom_complet = ?, mot_de_passe = ? WHERE id = ?");
        $updateStmt->execute([$data->nom_complet, $hashed_password, $utilisateur_id]);
    } else {
        // Aucun compte n'existe, on en crée un nouveau, marqué comme non vérifié
        $hashed_password = password_hash($data->mot_de_passe, PASSWORD_DEFAULT);
        $insertUserStmt = $pdo->prepare("INSERT INTO utilisateurs (nom_complet, email, mot_de_passe, is_verified) VALUES (?, ?, ?, FALSE)");
        $insertUserStmt->execute([$data->nom_complet, $data->email, $hashed_password]);
        $utilisateur_id = $pdo->lastInsertId();

        $annee_scolaire_par_defaut = date('Y') . '-' . (date('Y') + 1);
        $stmt = $pdo->prepare(
            "INSERT INTO etablissements (utilisateur_id, complexe, nom_etablissement, annee_scolaire) 
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$utilisateur_id, $data->complexe, $data->nom_etablissement, $annee_scolaire_par_defaut]);
    }

    // On supprime les anciens codes de vérification pour cet utilisateur pour n'en garder qu'un seul valide
    $pdo->prepare("DELETE FROM verification_codes WHERE utilisateur_id = ?")->execute([$utilisateur_id]);

    // On génère un nouveau code sécurisé à 6 chiffres
    $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    // On l'insère dans la base de données avec une date d'expiration de 15 minutes
    $insertCodeStmt = $pdo->prepare("INSERT INTO verification_codes (utilisateur_id, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
    $insertCodeStmt->execute([$utilisateur_id, $verification_code]);

    // --- Étape 6 : Envoi de l'e-mail de vérification ---
    $mail = new PHPMailer(true);

    // Configuration du serveur SMTP (Gmail dans cet exemple)
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'edtproservice@gmail.com';     // <<== REMPLACEZ PAR VOTRE EMAIL GMAIL
    $mail->Password   = 'gvqt gbea qkia gkoo';        // <<== REMPLACEZ PAR VOTRE MOT DE PASSE D'APPLICATION (16 caractères)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8'; // Pour une bonne gestion des accents

    // Destinataires
    $mail->setFrom('edtproservice@gmail.com', 'Service EDTpro'); // L'expéditeur
    $mail->addAddress($data->email, $data->nom_complet);                  // Le destinataire (nouvel utilisateur)

    // Contenu de l'e-mail
    $mail->isHTML(true);
    $mail->Subject = 'Votre code de vérification';
    $mail->Body = <<<HTML
<div style="font-family: Arial, sans-serif; background-color: #f9f9f9; padding: 30px; color: #333;">
    <div style="max-width: 600px; margin: auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <div style="padding: 20px; text-align: center;">
            <img src="https://bflplaygroundstore.blob.core.windows.net/files/6717bec0-fdb0-4ff1-80bf-923f052485ff/generations/d7b0800a-fecd-43f9-9e37-19d8cb30394f/raw?sv=2025-05-05&se=2025-07-27T18%3A44%3A19Z&sr=b&sp=r&sig=KZfk1jB6Zkxl8IJcTE4ceLIOiB%2FKh1tl%2BLw5PT7sBNk%3D" alt="EDTpro" style="height: 60px; margin-bottom: 20px;" />
            <h2 style="color: #2d3748;">Bienvenue sur la plateforme EDTpro !</h2>
        </div>
        <div style="padding: 20px; font-size: 16px; line-height: 1.6;">
            <p>Merci de vous être inscrit ! Pour finaliser votre inscription, veuillez utiliser le code de vérification ci-dessous :</p>
            <p style="background-color: #f2f2f2; border-radius: 5px; padding: 15px 30px; font-size: 28px; text-align: center; letter-spacing: 6px; font-weight: bold; margin: 30px 0;">
                {$verification_code}
            </p>
            <p>Ce code est valable pendant <strong>15 minutes</strong>.</p>
            <p>Si vous n'êtes pas à l'origine de cette demande, ignorez simplement cet e-mail en toute sécurité.</p>
            <p style="margin-top: 30px;">Cordialement,<br>L'équipe <strong>EDTpro</strong></p>
        </div>
        <div style="background: #f0f0f0; text-align: center; padding: 15px; font-size: 12px; color: #888;">
            &copy; EDTpro. Tous droits réservés.
        </div>
    </div>
</div>
HTML;

    $mail->AltBody = "Votre code de vérification est : {$verification_code}";

    $mail->send(); // Envoi de l'e-mail

    // Si tout s'est bien passé (y compris l'envoi de l'email), on valide la transaction
    $pdo->commit();
    
    // --- Étape 7 : Réponse au frontend ---
    // On indique au JavaScript que tout s'est bien passé et qu'il doit rediriger vers la page de vérification.
    echo json_encode([
        'success' => true, 
        'action' => 'verify',
        'email' => $data->email // On renvoie l'email pour pouvoir l'afficher sur la page de vérification
    ]);

} catch (Exception $e) {
    // En cas d'erreur (BDD ou envoi d'email), on annule toutes les opérations
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    http_response_code(500);
    // On renvoie un message d'erreur plus détaillé pour le débogage. Pour un site en production, on pourrait mettre un message plus générique.
    echo json_encode(['success' => false, 'message' => "Erreur lors de l'inscription: " . $e->getMessage()]);
}
?>