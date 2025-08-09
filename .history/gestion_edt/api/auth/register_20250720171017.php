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
        $insertEtabStmt = $pdo->prepare("INSERT INTO etablissements (utilisateur_id, nom_etablissement, annee_scolaire) VALUES (?, ?, ?)");
        $insertEtabStmt->execute([$utilisateur_id, $data->nom_etablissement, $annee_scolaire_par_defaut]);
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
    $mail->Username   = 'mouadnouzri@gmail.com';     // <<== REMPLACEZ PAR VOTRE EMAIL GMAIL
    $mail->Password   = 'pgst zzqh wlpo evuw';        // <<== REMPLACEZ PAR VOTRE MOT DE PASSE D'APPLICATION (16 caractères)
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8'; // Pour une bonne gestion des accents

    // Destinataires
    $mail->setFrom('mouadnouzri@gmail.com', 'Service EDTpro'); // L'expéditeur
    $mail->addAddress($data->email, $data->nom_complet);                  // Le destinataire (nouvel utilisateur)

    // Contenu de l'e-mail
    $mail->isHTML(true);
    $mail->Subject = 'Votre code de vérification';
    $mail->Body    = "
        <div style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <h2>Bienvenue sur la Platforme EDTpro !</h2>
            <p>Pour finaliser votre inscription, veuillez utiliser le code de vérification ci-dessous :</p>
            <p style='background-color: #f2f2f2; border-radius: 5px; padding: 10px 20px; font-size: 24px; text-align: center; letter-spacing: 5px; font-weight: bold;'>
                {$verification_code}
            </p>
            <p>Ce code est valide pour une durée de 15 minutes.</p>
            <p>Si vous n'êtes pas à l'origine de cette demande, veuillez ignorer cet e-mail.</p>
            <br>
            <p>Cordialement,<br>L'équipe du service EDTpro</p>
        </div>";
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