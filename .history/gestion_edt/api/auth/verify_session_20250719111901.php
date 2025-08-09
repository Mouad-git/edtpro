<?php
/**
 * Point de Contrôle de Session
 *
 * Ce script est appelé par le JavaScript au chargement de chaque page protégée.
 * Il utilise le gardien 'session_check.php' pour valider la session.
 */

// 1. On appelle notre gardien de sécurité.
// Il va démarrer la session et vérifier les identifiants.
// S'ils ne sont pas valides, le script s'arrêtera ici grâce au 'exit' dans session_check.php.
require_once 'session_check.php';

// 2. Si le script arrive jusqu'à cette ligne, cela signifie que le gardien a validé la session.
// L'utilisateur est donc bien connecté.

// On peut maintenant envoyer une réponse de succès au JavaScript.
http_response_code(200); // OK
echo json_encode(['success' => true]);

// Pas besoin de 'exit' ici, le script se termine normalement.
?>