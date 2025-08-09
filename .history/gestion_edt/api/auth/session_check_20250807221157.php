<?php

/**
 * Vérificateur de Session - Le Gardien de l'API
 *
 * Ce script a un seul rôle : vérifier si un utilisateur est actuellement connecté.
 * S'il ne l'est pas, il arrête immédiatement l'exécution et renvoie une erreur 403 (Interdit).
 * S'il est connecté, il ne fait rien et laisse le script qui l'a appelé continuer.
 *
 * Il doit être inclus avec require_once au tout début de chaque fichier PHP de l'API
 * qui nécessite une authentification.
 */

// 1. Démarrer ou reprendre la session existante
// C'est OBLIGATOIRE pour pouvoir accéder à la variable $_SESSION.
// Doit être appelé avant toute sortie HTML ou texte.
session_start();

// 2. Vérifier si les informations d'identification sont présentes dans la session
// Nous vérifions si 'utilisateur_id' ET 'etablissement_id' ont été définis lors de la connexion (login.php).
// Si l'une des deux manque, l'utilisateur n'est pas considéré comme correctement authentifié.
if (!isset($_SESSION['utilisateur_id']) || !isset($_SESSION['etablissement_id'])) {
    
    // 3. Si l'utilisateur n'est pas authentifié :
    
    // a) Envoyer un en-tête HTTP "403 Forbidden".
    // C'est la manière standard de dire à une API "Je te comprends, mais tu n'as pas le droit d'être ici".
    http_response_code(403);
    
    // b) Envoyer une réponse JSON claire au frontend.
    // Le JavaScript pourra lire ce message et savoir pourquoi l'accès a été refusé.
    echo json_encode([
        'success' => false,
        'message' => 'Accès non autorisé. Session invalide ou expirée.'
    ]);
    
    // c) Arrêter l'exécution du script.
    // C'est la partie la plus importante. Rien de ce qui se trouve dans le fichier
    // qui a appelé session_check.php ne sera exécuté. La porte est fermée.
    exit;
}

// 4. Si le script arrive jusqu'ici, cela signifie que les variables de session existent
// et que l'utilisateur est bien connecté. Le script qui a appelé ce fichier peut continuer son exécution normale.
?>