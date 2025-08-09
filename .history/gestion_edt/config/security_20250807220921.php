<?php
/**
 * Fichier de configuration de sécurité pour l'environnement de production.
 * À inclure au début de chaque script PHP accessible publiquement.
 */

// 1. Désactiver l'affichage des erreurs à l'utilisateur
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0); // On met à 0 pour ne rien afficher

// 2. Activer l'enregistrement des erreurs dans un fichier de log
ini_set('log_errors', 1);

// 3. Définir le chemin du fichier de log.
// IMPORTANT : Ce chemin doit être EN DEHORS de votre dossier public (htdocs, public_html).
// Par exemple, un niveau au-dessus de la racine de votre site.
// La fonction dirname(__DIR__) remonte d'un niveau.
$log_path = dirname(__DIR__) . '/php_errors.log';
ini_set('error_log', $log_path);
?>