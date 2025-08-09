<?php
// api/auth/register.php

// Inclure la configuration de la base de données
require_once '../../config/database.php';

// Récupérer le corps de la requête JSON
$data = json_decode(file_get_contents("php://input"));

// --- Validation simple ---
if (
    !isset($data->nom_complet) || empty($data->nom_complet) ||
    !isset($data->email) || !filter_var($data->email, FILTER_VALIDATE_EMAIL) ||
    !isset($data->mot_de_passe) || empty($data->mot_de_passe) ||
    !isset($data->nom_etablissement) || empty($data->nom_etablissement) ||
    !isset($data->annee_scolaire) || empty($data->annee_scolaire) ||
    !isset($data->espaces) || empty($data->espaces)
) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Veuillez remplir tous les champs correctement.']);
    exit;
}

// --- Vérifier si l'email existe déjà ---
$stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
$stmt->execute([$data->email]);
if ($stmt->fetch()) {
    http_response_code(409); // Conflict
    echo json_encode(['success' => false, 'message' => 'Cette adresse email est déjà utilisée.']);
    exit;
}

// Hacher le mot de passe - SÉCURITÉ OBLIGATOIRE
$hashed_password = password_hash($data->mot_de_passe, PASSWORD_DEFAULT);

// --- Démarrer une transaction pour garantir l'intégrité des données ---
$pdo->beginTransaction();

try {
    // 1. Insérer dans la table `utilisateurs`
    $stmt = $pdo->prepare("INSERT INTO utilisateurs (nom_complet, email, mot_de_passe) VALUES (?, ?, ?)");
    $stmt->execute([$data->nom_complet, $data->email, $hashed_password]);
    $utilisateur_id = $pdo->lastInsertId();

    // 2. Insérer dans la table `etablissements`
    $stmt = $pdo->prepare("INSERT INTO etablissements (utilisateur_id, nom_etablissement, annee_scolaire) VALUES (?, ?, ?)");
    $stmt->execute([$utilisateur_id, $data->nom_etablissement, $data->annee_scolaire]);
    $etablissement_id = $pdo->lastInsertId();

    // 3. Insérer dans la table `espaces`
    $espaces_list = explode("\n", $data->espaces);
    $stmt = $pdo->prepare("INSERT INTO espaces (etablissement_id, nom_espace) VALUES (?, ?)");
    foreach ($espaces_list as $nom_espace) {
        $nom_espace_trimmed = trim($nom_espace);
        if (!empty($nom_espace_trimmed)) {
            $stmt->execute([$etablissement_id, $nom_espace_trimmed]);
        }
    }

    // Si tout s'est bien passé, on valide la transaction
    $pdo->commit();

    // Envoyer une réponse de succès
    http_response_code(201); // Created
    echo json_encode(['success' => true, 'message' => 'Inscription réussie.']);

} catch (Exception $e) {
    // En cas d'erreur, on annule tout
    $pdo->rollBack();

    // Envoyer une réponse d'erreur
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => "Erreur lors de l'inscription: " . $e->getMessage()]);
}
?>