<?php
/**
 * API pour la Connexion d'un Utilisateur
 *
 * Ce script gère le processus de connexion en plusieurs étapes :
 * 1. Démarre la session.
 * 2. Valide les données (email, mot de passe) reçues du formulaire.
 * 3. Cherche l'utilisateur dans la base de données par son e-mail.
 * 4. Vérifie si l'utilisateur existe et si le mot de passe est correct avec password_verify().
 * 5. Vérifie si le compte de l'utilisateur a été activé par e-mail (is_verified).
 * 6. VÉRIFICATION CLÉ : Vérifie si l'utilisateur a terminé sa configuration initiale (is_setup_complete).
 *    - Si NON, le redirige vers la page de configuration (setup.html).
 *    - Si OUI, continue le processus normal.
 * 7. Compte le nombre d'établissements liés à l'utilisateur.
 *    - Si 1, le redirige directement vers l'application (admin.html).
 *    - Si >1, le redirige vers la page de sélection d'établissement.
 */

// Étape 1 : Démarrer la session. C'est la première chose à faire.
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
    // Étape 3 : Chercher l'utilisateur par e-mail
    // On ajoute 'is_setup_complete' à la liste des colonnes à récupérer
$stmt = $pdo->prepare("SELECT id, mot_de_passe, is_verified, is_setup_complete FROM utilisateurs WHERE email = ?");
    $stmt->execute([$data->email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: text/plain'); 
    
    echo "--- DÉBUT DU DÉBOGAGE ---\n\n";

    // var_dump est une fonction magique qui affiche le contenu ET le type d'une variable.
    var_dump($user); 

    echo "\n--- FIN DU DÉBOGAGE ---";

    exit; // TRÈS IMPORTANT: On arrête le script ici pour voir le résultat du débogage.
    // --- FIN DU BLOC DE DÉBOGAGE ---

    // Étape 4 : Vérifier si l'utilisateur existe et si le mot de passe est correct
    if (!$user || !password_verify($data->mot_de_passe, $user['mot_de_passe'])) {
        http_response_code(401); // Unauthorized
        echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect.']);
        exit;
    }

    // Étape 5 : Vérifier si le compte a été activé par e-mail
    if (!$user['is_verified']) {
        http_response_code(403); // Forbidden
        echo json_encode(['success' => false, 'message' => 'Votre compte n\'est pas encore vérifié. Veuillez consulter l\'e-mail que nous vous avons envoyé.']);
        exit;
    }
    
    // Si on arrive ici, l'utilisateur est authentifié. On peut enregistrer son ID dans la session.
    $_SESSION['utilisateur_id'] = $user['id'];

    // Étape 6 : VÉRIFICATION CLÉ - Le setup a-t-il été complété ?
    if (!$user['is_setup_complete']) {
        // La configuration n'est pas terminée. On envoie l'utilisateur vers la page de setup.
        echo json_encode([
            'success' => true,
            'action' => 'redirect',
            'url' => '../../public/setup.html' // L'URL de redirection
        ]);
        exit; // On arrête le script ici.
    }
    
    // Si on arrive ici, l'utilisateur est authentifié ET a déjà configuré son compte.
    // On procède à la logique de sélection d'établissement.

    // Étape 7 : Compter les établissements
    $stmt = $pdo->prepare("SELECT id, nom_etablissement FROM etablissements WHERE utilisateur_id = ?");
    $stmt->execute([$user['id']]);
    $etablissements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count = count($etablissements);

    if ($count === 1) {
        // L'utilisateur n'a qu'un seul établissement, on le connecte directement dessus.
        $_SESSION['etablissement_id'] = $etablissements[0]['id'];
        echo json_encode([
            'success' => true,
            'action' => 'redirect',
            'url' => 'admin.html'
        ]);
    } elseif ($count > 1) {
        // L'utilisateur a plusieurs établissements, on l'envoie sur la page de sélection.
        // On ne met PAS encore d'etablissement_id dans la session.
        echo json_encode([
            'success' => true,
            'action' => 'redirect',
            'url' => 'select_etablissement.html'
        ]);
    } else {
        // Cas rare : l'utilisateur est vérifié mais n'a aucun établissement.
        // Cela peut arriver si quelque chose a mal tourné à l'inscription.
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Aucun établissement n\'est associé à ce compte. Veuillez contacter le support.']);
    }

} catch (Exception $e) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['success' => false, 'message' => 'Erreur du serveur: ' . $e->getMessage()]);
}
?>