# 🚀 Guide de Déploiement EDTpro

## Optimisation du dossier vendor

Le dossier `vendor` contient toutes les dépendances PHP. Pour réduire sa taille et optimiser le déploiement :

### Dépendances utilisées :
- **PhpSpreadsheet** : Lecture/écriture de fichiers Excel
- **PHPMailer** : Envoi d'emails

### Étapes d'optimisation :

1. **Installation optimisée** :
```bash
composer install --no-dev --optimize-autoloader
```

2. **Exécution du script d'optimisation** :
```bash
php optimize_vendor.php
```

3. **Script de déploiement automatique** :
```bash
chmod +x deploy.sh
./deploy.sh
```

### Fichiers supprimés lors de l'optimisation :

#### Dossiers supprimés :
- `vendor/*/tests/` - Tests unitaires
- `vendor/*/examples/` - Exemples de code
- `vendor/*/samples/` - Exemples d'utilisation
- `vendor/*/docs/` - Documentation

#### Fichiers supprimés :
- `README.md` - Documentation
- `CHANGELOG.md` - Historique des versions
- `LICENSE` - Licences
- `*.md` - Fichiers de documentation

### Réduction de taille attendue :
- **Avant optimisation** : ~50-80 MB
- **Après optimisation** : ~15-25 MB
- **Gain** : 60-70% de réduction

## Structure de déploiement

```
gestion_edt/
├── api/                    # API backend
├── config/                 # Configuration
├── public/                 # Interface utilisateur
├── vendor/                 # Dépendances optimisées
├── composer.json           # Dépendances
├── .htaccess              # Configuration Apache
└── README_DEPLOIEMENT.md  # Ce fichier
```

## Configuration serveur

### Prérequis :
- PHP 8.1+
- MySQL/MariaDB
- Extensions PHP : `ext-ctype`, `ext-dom`, `ext-fileinfo`, `ext-gd`, `ext-iconv`, `ext-libxml`, `ext-mbstring`, `ext-simplexml`, `ext-xml`, `ext-xmlreader`, `ext-xmlwriter`, `ext-zip`, `ext-zlib`

### Permissions :
```bash
chmod 755 -R gestion_edt/
chmod 644 gestion_edt/config/database.php
```

### Variables d'environnement :
Créer un fichier `.env` avec :
```
DB_HOST=localhost
DB_NAME=votre_base
DB_USER=votre_utilisateur
DB_PASS=votre_mot_de_passe
SMTP_HOST=smtp.gmail.com
SMTP_USER=votre_email@gmail.com
SMTP_PASS=votre_mot_de_passe_app
```

## Déploiement rapide

1. **Optimiser le projet** :
```bash
./deploy.sh
```

2. **Transférer les fichiers** :
```bash
# Via FTP/SFTP ou Git
```

3. **Configurer la base de données** :
- Importer le schéma SQL
- Configurer les accès

4. **Tester l'application** :
- Vérifier l'accès à l'interface
- Tester l'upload de fichiers Excel
- Tester l'envoi d'emails

## Dépannage

### Erreurs courantes :
- **500 Internal Server Error** : Vérifier les permissions et les extensions PHP
- **Fatal error: Class not found** : Vérifier que `vendor/autoload.php` est inclus
- **Upload failed** : Vérifier les permissions du dossier temporaire

### Logs utiles :
- `logs/security.log` - Logs de sécurité
- `logs/rate_limit_*.log` - Logs de limitation de débit

## Support

Pour toute question sur le déploiement, consultez :
- La documentation PHP
- Les logs d'erreur du serveur
- La documentation de PhpSpreadsheet et PHPMailer 