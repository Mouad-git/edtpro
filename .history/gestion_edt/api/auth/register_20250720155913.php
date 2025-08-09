<?php
// api/auth/register.php

require_once '../../config/database.php';
require_once '../../vendor/autoload.php'; // Pour PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ... (votre code de validation des données du formulaire reste ici) ...

// --- Début de la logique de vérification ---

// On vérifie si un utilisateur non vérifié existe déjà avec cet email
$stmt = $pdo->prepare("SELECT id, is_verified FROM utilisateurs WHERE email = ?");
$stmt->execute([$data->email]);
$existingUser = $stmt->fetch();

if ($existingUser && $existingUser['is_verified']) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Cette adresse email est déjà utilisée.']);
    exit;
}

$pdo->beginTransaction();
try {
    if ($existingUser) { // Si un compte non vérifié existe, on le réutilise
        $utilisateur_id = $existingUser['id'];
    } else { // Sinon, on crée un nouvel utilisateur non vérifié
        $hashed_password = password_hash($data->mot_de_passe, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom_complet, email, mot_de_passe, is_verified) VALUES (?, ?, ?, FALSE)");
        $stmt->execute([$data->nom_complet, $data->email, $hashed_password]);
        $utilisateur_id = $pdo->lastInsertId();

        $annee_scolaire_par_defaut = date('Y') . '-' . (date('Y') + 1);
        $stmt = $pdo->prepare("INSERT INTO etablissements (utilisateur_id, nom_etablissement, annee_scolaire) VALUES (?, ?, ?)");
        $stmt->execute([$utilisateur_id, $data->nom_etablissement, $annee_scolaire_par_defaut]);
    }

    // Générer un code de vérification à 6 chiffres
    $verification_code = random_int(100000, 999999);

    // Définir une date d'expiration (ex: 15 minutes)
    $stmt = $pdo->prepare("INSERT INTO verification_codes (utilisateur_id, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
    $stmt->execute([$utilisateur_id, $verification_code]);

    // --- Envoi de l'e-mail ---
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'votre.email@gmail.com'; // VOTRE ADRESSE GMAIL
    $mail->Password   = 'abcd efgh ijkl mnop';   // VOTRE MOT DE PASSE D'APPLICATION
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('votre.email@gmail.com', 'Gestionnaire EDT');
    $mail->addAddress($data->email, $data->nom_complet);

    $mail->isHTML(true);
    $mail->Subject = 'Votre code de vérification pour le Gestionnaire EDT';
    $mail->Body    = "Bonjour,<br><br>Votre code de vérification est : <b>{$verification_code}</b><br><br>Ce code expirera dans 15 minutes.<br><br>Cordialement,<br>L'équipe du Gestionnaire EDT";
    $mail->AltBody = "Votre code de vérification est : {$verification_code}";

    $mail->send();
    
    $pdo->commit();
    
    // On dit au frontend de rediriger vers la page de vérification
    echo json_encode([
        'success' => true, 
        'action' => 'verify',
        'email' => $data->email // On renvoie l'email pour le passer dans l'URL
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Erreur: " . $e->getMessage()]);
}
?>