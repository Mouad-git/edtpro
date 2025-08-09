<?php
// api/auth/login.php

// Étape 1 : Démarrer la session. C'est la toute première chose à faire.
session_start();

// Inclure les fichiers nécessaires
require_once '../../config/database.php';

// Indiquer que la réponse sera toujours au format JSON
header('Content-Type: application/json');

// Récupérer les données envoyées par le JavaScript
$data = json_decode(file_get_contents("php://input"));

// Étape 2 : Valider les données reçues
if (!isset($data->email) || !filter_var($data->email, FILTER_VALIDATE_EMAIL) || !isset($data->mot_de_passe)) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Veuillez fournir un email et un mot de passe valides.']);
    exit;
}

try {
    // Étape 3 : Chercher l'utilisateur et TOUTES ses informations en une seule fois
    $stmt = $pdo->prepare(
        "SELECT id, mot_de_passe, is_verified, is_setup_complete, status, role 
         FROM utilisateurs WHERE email = ?"
    );
    $stmt->execute([$data->email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Étape 4 : Vérifier le mot de passe
    if (!$user || !password_verify($data->mot_de_passe, $user['mot_de_passe'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect.']);
        exit;
    }

    // Étape 5 : Vérifier le statut du compte
    if ($user['status'] === 'pending') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Votre compte est en attente d\'approbation par un administrateur.']);
        exit;
    }
    if ($user['status'] === 'rejected') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Votre inscription a été refusée.']);
        exit;
    }
    // Si 'approved', on continue.

    // Si on arrive ici, l'utilisateur est authentifié. On peut stocker ses infos de base dans la session.
    $_SESSION['utilisateur_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];

    // --- NOUVEAU : GESTION DES RÔLES ---
    if ($user['role'] === 'admin') {
        // L'utilisateur est un admin, on le redirige vers le tableau de bord admin.
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'admin_dashboard.html']);
        exit;
    }
    // --- FIN GESTION DES RÔLES ---
    
    // Si on arrive ici, l'utilisateur est forcément un 'director'.

    // Étape 6 : VÉRIFICATION CLÉ - Le setup a-t-il été complété ?
    if (!$user['is_setup_complete']) {
        // On récupère l'ID de son établissement (qui a été créé à l'inscription)
        $stmt_etab = $pdo->prepare("SELECT id FROM etablissements WHERE utilisateur_id = ? LIMIT 1");
        $stmt_etab->execute([$user['id']]);
        $etablissement = $stmt_etab->fetch(PDO::FETCH_ASSOC);

        if (!$etablissement) {
            // Sécurité : si pas d'établissement, on ne peut pas continuer.
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Erreur critique : aucun établissement lié à ce nouveau compte.']);
            exit;
        }
        
        // On met à jour la session avec les deux ID avant de rediriger
        $_SESSION['etablissement_id'] = $etablissement['id'];
        
        echo json_encode([
            'success' => true,
            'action' => 'redirect',
            'url' => 'setup.html'
        ]);
        exit;
    }
    
    // Si on arrive ici, l'utilisateur est un directeur DÉJÀ CONFIGURÉ.
    
    // Étape 7 : Compter les établissements pour la redirection finale
    $stmt = $pdo->prepare("SELECT id, nom_etablissement FROM etablissements WHERE utilisateur_id = ?");
    $stmt->execute([$user['id']]);
    $etablissements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = count($etablissements);

    if ($count === 1) {
        $_SESSION['etablissement_id'] = $etablissements[0]['id'];
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'emploi.html']);
    } elseif ($count > 1) {
        // On ne met pas etablissement_id ici, l'utilisateur doit choisir.
        echo json_encode(['success' => true, 'action' => 'redirect', 'url' => 'select_etablissement.html']);
    } else {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Aucun établissement n\'est associé à ce compte.']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur du serveur: ' . $e->getMessage()]);
}
?>