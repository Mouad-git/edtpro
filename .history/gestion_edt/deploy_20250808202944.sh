#!/bin/bash

echo "🚀 Script de déploiement optimisé pour EDTpro"

# Vérification de la présence de Composer
if ! command -v composer &> /dev/null; then
    echo "❌ Composer n'est pas installé. Veuillez l'installer d'abord."
    exit 1
fi

echo "📦 Installation des dépendances..."
composer install --no-dev --optimize-autoloader

echo "🔧 Optimisation du dossier vendor..."
php optimize_vendor.php

echo "🧹 Nettoyage des fichiers temporaires..."
find . -name "*.log" -delete
find . -name ".DS_Store" -delete
find . -name "Thumbs.db" -delete

echo "📊 Taille finale du projet:"
du -sh .

echo "✅ Déploiement optimisé terminé !"
echo ""
echo "📋 Fichiers essentiels à transférer:"
echo "- Tous les fichiers PHP (.php)"
echo "- Dossier public/ (interface utilisateur)"
echo "- Dossier api/ (API backend)"
echo "- Dossier config/ (configuration)"
echo "- Dossier vendor/ (dépendances optimisées)"
echo "- composer.json"
echo "- .htaccess (si présent)"
echo ""
echo "⚠️  N'oubliez pas de:"
echo "1. Configurer votre base de données"
echo "2. Vérifier les permissions des dossiers"
echo "3. Configurer les variables d'environnement" 