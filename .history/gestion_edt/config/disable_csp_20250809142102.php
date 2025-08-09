<?php
/**
 * Fichier temporaire pour désactiver la CSP en développement
 * À utiliser uniquement en cas de problème avec les CDN
 * 
 * Pour l'utiliser, ajoutez cette ligne au début de vos fichiers PHP :
 * $_ENV['DISABLE_CSP'] = true;
 */

// Désactiver la CSP temporairement
$_ENV['DISABLE_CSP'] = true;

echo "CSP désactivée temporairement pour le développement.\n";
echo "N'oubliez pas de réactiver la CSP avant la production !\n";
?> 