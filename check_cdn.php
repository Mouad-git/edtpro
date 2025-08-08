<?php
/**
 * Script pour scanner et détecter tous les CDN utilisés dans l'application
 */

echo "=== SCANNER DE CDN ===\n\n";

$public_dir = __DIR__ . '/public';
$cdn_patterns = [
    'script' => '/<script[^>]*src=["\']([^"\']+)["\'][^>]*>/i',
    'link' => '/<link[^>]*href=["\']([^"\']+)["\'][^>]*>/i',
    'img' => '/<img[^>]*src=["\']([^"\']+)["\'][^>]*>/i'
];

$found_cdns = [];

// Scanner tous les fichiers HTML
$html_files = glob($public_dir . '/*.html');
foreach ($html_files as $file) {
    $content = file_get_contents($file);
    $filename = basename($file);
    
    echo "Scanning: $filename\n";
    
    foreach ($cdn_patterns as $type => $pattern) {
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $url) {
                if (strpos($url, 'http') === 0) {
                    $domain = parse_url($url, PHP_URL_HOST);
                    $found_cdns[$domain][] = [
                        'url' => $url,
                        'file' => $filename,
                        'type' => $type
                    ];
                    echo "  Found: $url ($type)\n";
                }
            }
        }
    }
}

echo "\n=== CDN DÉTECTÉS ===\n";
foreach ($found_cdns as $domain => $urls) {
    echo "\n$domain:\n";
    foreach ($urls as $item) {
        echo "  - {$item['url']} (in {$item['file']}, type: {$item['type']})\n";
    }
}

echo "\n=== CSP RECOMMANDÉE ===\n";
$domains = array_keys($found_cdns);
$script_domains = [];
$style_domains = [];

foreach ($found_cdns as $domain => $urls) {
    foreach ($urls as $item) {
        if ($item['type'] === 'script') {
            $script_domains[] = "https://$domain";
        } elseif ($item['type'] === 'link') {
            $style_domains[] = "https://$domain";
        }
    }
}

$script_domains = array_unique($script_domains);
$style_domains = array_unique($style_domains);

echo "script-src 'self' 'unsafe-inline' " . implode(' ', $script_domains) . "\n";
echo "style-src 'self' 'unsafe-inline' " . implode(' ', $style_domains) . "\n";

echo "\n=== MISE À JOUR AUTOMATIQUE ===\n";
echo "Voulez-vous que je mette à jour automatiquement la CSP ? (y/n): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim($line) === 'y' || trim($line) === 'Y') {
    // Mettre à jour la CSP dans security.php
    $security_file = __DIR__ . '/config/security.php';
    $content = file_get_contents($security_file);
    
    $new_csp = "header('Content-Security-Policy: default-src \\'self\\'; script-src \\'self\\' \\'unsafe-inline\\' " . implode(' ', $script_domains) . "; style-src \\'self\\' \\'unsafe-inline\\' " . implode(' ', $style_domains) . "; img-src \\'self\\' data: https:; font-src \\'self\\' https:; connect-src \\'self\\';');";
    
    // Remplacer la ligne CSP existante
    $pattern = '/header\(\'Content-Security-Policy:.*?\'\);/s';
    $content = preg_replace($pattern, $new_csp, $content);
    
    file_put_contents($security_file, $content);
    echo "CSP mise à jour dans config/security.php\n";
} else {
    echo "Mise à jour annulée.\n";
}

echo "\n=== FIN DU SCAN ===\n";
?> 