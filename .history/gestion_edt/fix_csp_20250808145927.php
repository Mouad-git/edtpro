<?php
/**
 * Script de diagnostic et correction des problèmes CSP
 */

echo "=== DIAGNOSTIC CSP ===\n\n";

// Vérifier les CDN utilisés dans votre application
$cdn_urls = [
    'https://unpkg.com/xlsx/dist/xlsx.full.min.js',
    'https://cdn.jsdelivr.net/npm/chart.js',
    'https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'
];

echo "CDN détectés dans votre application :\n";
foreach ($cdn_urls as $url) {
    $domain = parse_url($url, PHP_URL_HOST);
    $path = parse_url($url, PHP_URL_PATH);
    echo "- $domain$path\n";
}

echo "\n=== SOLUTIONS ===\n\n";

echo "1. SOLUTION IMMÉDIATE - Désactiver temporairement la CSP :\n";
echo "   Ajoutez cette ligne au début de vos fichiers PHP :\n";
echo "   \$_ENV['DISABLE_CSP'] = true;\n\n";

echo "2. SOLUTION RECOMMANDÉE - Télécharger les fichiers localement :\n";
echo "   - Téléchargez les fichiers depuis les CDN\n";
echo "   - Placez-les dans public/assets/js/ et public/assets/css/\n";
echo "   - Mettez à jour les références dans vos fichiers HTML\n\n";

echo "3. SOLUTION ALTERNATIVE - CSP plus permissive :\n";
echo "   La CSP a été ajustée pour permettre les CDN nécessaires.\n\n";

echo "=== ACTIONS RECOMMANDÉES ===\n\n";

echo "1. Pour un développement rapide, désactivez temporairement la CSP :\n";
echo "   include 'config/disable_csp.php';\n\n";

echo "2. Pour la production, téléchargez les fichiers localement :\n";
echo "   - xlsx.full.min.js\n";
echo "   - chart.js\n";
echo "   - chartjs-plugin-datalabels.min.js\n";
echo "   - font-awesome CSS\n\n";

echo "3. Vérifiez que tous les CDN sont autorisés dans la CSP.\n\n";

// Test de la CSP actuelle
echo "=== TEST DE LA CSP ACTUELLE ===\n";
$_ENV['APP_ENV'] = 'development';
$_ENV['DISABLE_CSP'] = false;

require_once 'config/security.php';

echo "CSP configurée pour le développement.\n";
echo "Si vous avez encore des erreurs, utilisez la solution 1.\n\n";

echo "=== FICHIERS À MODIFIER ===\n";
echo "1. public/emploi.html - Mettre à jour les références CDN\n";
echo "2. public/emploi.js - Vérifier les dépendances Chart.js\n";
echo "3. Tous les fichiers HTML utilisant des CDN externes\n\n";

echo "=== COMMANDES UTILES ===\n";
echo "Pour télécharger les fichiers localement :\n";
echo "wget https://unpkg.com/xlsx/dist/xlsx.full.min.js -O public/assets/js/xlsx.full.min.js\n";
echo "wget https://cdn.jsdelivr.net/npm/chart.js -O public/assets/js/chart.js\n";
echo "wget https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css -O public/assets/css/font-awesome.min.css\n\n";

echo "=== FIN DU DIAGNOSTIC ===\n";
?> 