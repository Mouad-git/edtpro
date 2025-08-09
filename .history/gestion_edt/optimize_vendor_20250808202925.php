<?php
/**
 * Script d'optimisation du dossier vendor pour l'hébergement
 * Ce script supprime les fichiers non essentiels pour réduire la taille
 */

echo "🔧 Optimisation du dossier vendor pour l'hébergement...\n";

// Dossiers à supprimer (tests, docs, exemples)
$dirsToRemove = [
    'vendor/phpoffice/phpspreadsheet/tests',
    'vendor/phpoffice/phpspreadsheet/samples',
    'vendor/phpoffice/phpspreadsheet/docs',
    'vendor/phpmailer/phpmailer/examples',
    'vendor/phpmailer/phpmailer/test',
    'vendor/phpmailer/phpmailer/docs',
    'vendor/markbaker/complex/examples',
    'vendor/markbaker/matrix/examples',
    'vendor/maennchen/zipstream-php/test',
    'vendor/composer/pcre/test',
    'vendor/psr/http-client/test',
    'vendor/psr/http-factory/test',
    'vendor/psr/http-message/test',
    'vendor/psr/simple-cache/test'
];

// Fichiers à supprimer
$filesToRemove = [
    'vendor/phpoffice/phpspreadsheet/CHANGELOG.md',
    'vendor/phpoffice/phpspreadsheet/CONTRIBUTING.md',
    'vendor/phpoffice/phpspreadsheet/README.md',
    'vendor/phpoffice/phpspreadsheet/phpdoc.dist.xml',
    'vendor/phpmailer/phpmailer/README.md',
    'vendor/phpmailer/phpmailer/COMMITMENT',
    'vendor/phpmailer/phpmailer/LICENSE',
    'vendor/markbaker/complex/README.md',
    'vendor/markbaker/complex/license.md',
    'vendor/markbaker/matrix/README.md',
    'vendor/markbaker/matrix/buildPhar.php',
    'vendor/maennchen/zipstream-php/README.md',
    'vendor/maennchen/zipstream-php/LICENSE',
    'vendor/composer/pcre/README.md',
    'vendor/composer/pcre/LICENSE',
    'vendor/psr/http-client/CHANGELOG.md',
    'vendor/psr/http-client/README.md',
    'vendor/psr/http-factory/README.md',
    'vendor/psr/http-message/CHANGELOG.md',
    'vendor/psr/http-message/README.md',
    'vendor/psr/simple-cache/README.md'
];

// Suppression des dossiers
foreach ($dirsToRemove as $dir) {
    if (is_dir($dir)) {
        echo "🗑️  Suppression du dossier: $dir\n";
        deleteDirectory($dir);
    }
}

// Suppression des fichiers
foreach ($filesToRemove as $file) {
    if (file_exists($file)) {
        echo "🗑️  Suppression du fichier: $file\n";
        unlink($file);
    }
}

// Optimisation de l'autoloader
echo "⚡ Optimisation de l'autoloader...\n";
system('composer dump-autoload --optimize --classmap-authoritative --no-dev');

echo "✅ Optimisation terminée !\n";
echo "📊 Taille du dossier vendor après optimisation:\n";
system('du -sh vendor');

function deleteDirectory($dir) {
    if (!is_dir($dir)) {
        return;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            deleteDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}
?> 