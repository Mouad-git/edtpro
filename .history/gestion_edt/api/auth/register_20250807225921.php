<?php
// api/auth/register.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PDOException;

header('Content-Type: application/json');
$data = json_decode(file_get_contents("php://input"));

// --- Étape 1 : Validation des Données (inchangée) ---
if (
    !isset($data->nom_complet) || empty($data->nom_complet) ||
    !isset($data->complexe) || empty($data->complexe) ||
    !isset($data->email) || !filter_var($data->email, FILTER_VALIDATE_EMAIL) ||
    !isset($data->mot_de_passe) || empty($data->mot_de_passe) ||
    !isset($data->nom_etablissement) || empty($data->nom_etablissement) ||
    strlen($data->mot_de_passe) < 6
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs correctement (mot de passe 6 caractères min).']);
    exit;
}

try {
    // --- Étape 2 : Vérification de l'Utilisateur Existant (AVANT la transaction) ---
    $stmt = $pdo->prepare("SELECT id, is_verified FROM utilisateurs WHERE email = ?");
    $stmt->execute([$data->email]);
    $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingUser && $existingUser['is_verified']) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Cette adresse email est déjà utilisée par un compte actif.']);
        exit;
    }

    // --- Étape 3 : Opérations d'Écriture dans une Transaction ---
    $pdo->beginTransaction();

    if ($existingUser) {
        $utilisateur_id = $existingUser['id'];
        $hashed_password = password_hash($data->mot_de_passe, PASSWORD_DEFAULT);
        $updateStmt = $pdo->prepare("UPDATE utilisateurs SET nom_complet = ?, mot_de_passe = ? WHERE id = ?");
        $updateStmt->execute([$data->nom_complet, $hashed_password, $utilisateur_id]);
    } else {
        $hashed_password = password_hash($data->mot_de_passe, PASSWORD_DEFAULT);
        $insertUserStmt = $pdo->prepare("INSERT INTO utilisateurs (nom_complet, email, mot_de_passe) VALUES (?, ?, ?)");
        $insertUserStmt->execute([$data->nom_complet, $data->email, $hashed_password]);
        $utilisateur_id = $pdo->lastInsertId();

        $annee_scolaire_par_defaut = date('Y') . '-' . (date('Y') + 1);
        $insertEtabStmt = $pdo->prepare("INSERT INTO etablissements (utilisateur_id, complexe, nom_etablissement, annee_scolaire) VALUES (?, ?, ?, ?)");
        $insertEtabStmt->execute([$utilisateur_id, $data->complexe, $data->nom_etablissement, $annee_scolaire_par_defaut]);
    }

    $pdo->prepare("DELETE FROM verification_codes WHERE utilisateur_id = ?")->execute([$utilisateur_id]);
    $verification_code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $insertCodeStmt = $pdo->prepare("INSERT INTO verification_codes (utilisateur_id, code, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 15 MINUTE))");
    $insertCodeStmt->execute([$utilisateur_id, $verification_code]);

    // --- Étape 4 : Envoi de l'e-mail ---
    $mail = new PHPMailer(true);
    // Configuration du serveur SMTP
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'edtproservice@gmail.com';
    $mail->Password   = 'gvqt gbea qkia gkoo'; // Votre mot de passe d'application
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';
    
    // Destinataires et contenu (votre excellent template HTML)
    $mail->setFrom('edtproservice@gmail.com', 'Service EDTpro');
    $mail->addAddress($data->email, $data->nom_complet);
    $mail->isHTML(true);
    $mail->Subject = 'Votre code de vérification pour EDTpro';
    $mail->Body = <<<HTML
    ... (VOTRE TEMPLATE HTML COMPLET VA ICI) ...
    <p style="...font-weight: bold; ...">{$verification_code}</p>
    ...
HTML;
    $mail->AltBody = "Votre code de vérification est : {$verification_code}";

    $mail->send(); // Tente d'envoyer l'e-mail. Si ça échoue, une PHPMailerException sera lancée.

    // Si on arrive ici, l'e-mail est parti. On peut valider la transaction.
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'action' => 'verify',
        'email' => $data->email
    ]);

} catch (PDOException $e) {
    // Erreur spécifique à la base de données
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Erreur de base de données: " . $e->getMessage()]);
} catch (PHPMailerException $e) {
    // Erreur spécifique à l'envoi de l'e-mail
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Erreur lors de l'envoi de l'e-mail de vérification: " . $mail->ErrorInfo]);
} catch (Exception $e) {
    // Toutes les autres erreurs
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Une erreur inattendue est survenue: " . $e->getMessage()]);
}
?>