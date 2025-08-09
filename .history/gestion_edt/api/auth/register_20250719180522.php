<?php
// api/auth/register.php

require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));

// --- Validation mise à jour (vérification du mot de passe) ---
if (
    // ... (les autres vérifications restent les mêmes) ...
    !isset($data->mot_de_passe) || empty($data->mot_de_passe) ||
    !isset($data->nom_etablissement) || empty($data->nom_etablissement)
) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs correctement.']);
    exit;
}

// --- AJOUTÉ : Validation de la longueur du mot de passe côté serveur ---
if (strlen($data->mot_de_passe) < 6) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Le mot de passe doit contenir au moins 6 caractères.']);
    exit;
}

// Le reste du script est identique car la validation de la correspondance est gérée par le JS,
// et nous n'avons pas besoin d'enregistrer le mot de passe de confirmation dans la base de données.

// --- Vérification de l'email (inchangé) ---
$stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
$stmt->execute([$data->email]);
if ($stmt->fetch()) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Cette adresse email est déjà utilisée.']);
    exit;
}

// Hachage du mot de passe (inchangé)
$hashed_password = password_hash($data->mot_de_passe, PASSWORD_DEFAULT);

// --- Transaction (inchangée) ---
$pdo->beginTransaction();
try {
    // 1. Insérer dans la table `utilisateurs`
    $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom_complet, email, mot_de_passe) VALUES (?, ?, ?)");
    $stmt->execute([$data->nom_complet, $data->email, $hashed_password]);
    $utilisateur_id = $pdo->lastInsertId();

    // 2. Insérer dans la table `etablissements`
    $annee_scolaire_par_defaut = date('Y') . '-' . (date('Y') + 1);
    $stmt = $pdo->prepare("INSERT INTO etablissements (utilisateur_id, nom_etablissement, annee_scolaire) VALUES (?, ?, ?)");
    $stmt->execute([$utilisateur_id, $data->nom_etablissement, $annee_scolaire_par_defaut]);

    $pdo->commit();
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Inscription réussie.']);

} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Erreur lors de l'inscription: " . $e->getMessage()]);
}
?>