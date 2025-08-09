# 📅 EDTpro - Système de Gestion d'Emploi du Temps

Un système complet de gestion d'emploi du temps développé en PHP avec une interface moderne et intuitive.

## 🚀 Fonctionnalités

- **👥 Gestion des utilisateurs** : Inscription, connexion, profils
- **📊 Tableau de bord administrateur** : Approbation des directeurs
- **📈 Suivi d'avancement** : Monitoring des programmes
- **📄 Import Excel** : Traitement de fichiers PhpSpreadsheet
- **📧 Notifications email** : Système d'envoi via PHPMailer
- **🔐 Sécurité** : Protection CSRF, validation des données

## 🛠️ Technologies

- **Backend** : PHP 8.1+
- **Frontend** : HTML5, CSS3, JavaScript (Tailwind CSS)
- **Base de données** : MySQL/MariaDB
- **Dépendances** :
  - PhpSpreadsheet (lecture/écriture Excel)
  - PHPMailer (envoi d'emails)

## 📦 Installation

### Prérequis
- PHP 8.1 ou supérieur
- MySQL/MariaDB
- Composer
- Extensions PHP : `ext-ctype`, `ext-dom`, `ext-fileinfo`, `ext-gd`, `ext-iconv`, `ext-libxml`, `ext-mbstring`, `ext-simplexml`, `ext-xml`, `ext-xmlreader`, `ext-xmlwriter`, `ext-zip`, `ext-zlib`

### Installation locale

1. **Cloner le repository** :
```bash
git clone https://github.com/votre-username/gestion_edt.git
cd gestion_edt
```

2. **Installer les dépendances** :
```bash
composer install
```

3. **Configuration** :
```bash
# Copier et configurer la base de données
cp config/database.example.php config/database.php
# Éditer avec vos paramètres de connexion
```

4. **Importer la base de données** :
```sql
-- Importer le schéma SQL (à fournir)
```

5. **Configurer le serveur web** :
```apache
# Apache - .htaccess inclus
# Pointer vers le dossier public/
```

## 🚀 Déploiement

### Hébergement recommandé
- InfinityFree (gratuit)
- Hostinger
- OVH
- Tout hébergeur supportant PHP 8.1+

### Optimisation pour production
```bash
# Installation optimisée
composer install --no-dev --optimize-autoloader

# Permissions
chmod 755 -R .
chmod 644 config/database.php
```

## 📂 Structure du projet

```
gestion_edt/
├── api/                    # API backend
│   ├── auth/              # Authentification
│   ├── admin/             # Administration
│   ├── data/              # Gestion des données
│   ├── profile/           # Profils utilisateurs
│   └── setup/             # Configuration initiale
├── config/                # Configuration
│   └── database.php       # Configuration BDD
├── public/                # Interface utilisateur
│   ├── assets/            # CSS, JS, images
│   ├── admin_dashboard.html
│   ├── emploi.html
│   └── ...
├── vendor/                # Dépendances Composer
├── logs/                  # Logs applicatifs
├── composer.json          # Dépendances
└── README.md             # Documentation
```

## 🔧 Configuration

### Variables d'environnement
Créer un fichier `.env` :
```bash
DB_HOST=localhost
DB_NAME=votre_base
DB_USER=votre_utilisateur
DB_PASS=votre_mot_de_passe
SMTP_HOST=smtp.gmail.com
SMTP_USER=votre_email@gmail.com
SMTP_PASS=votre_mot_de_passe_app
```

### Base de données
```sql
-- Configuration recommandée
CREATE DATABASE gestion_edt CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## 📱 Interface

- **Design moderne** : Interface responsive avec Tailwind CSS
- **Tableau de bord** : Vue d'ensemble des statistiques
- **Gestion Excel** : Import/export de données
- **Notifications** : Système d'alertes en temps réel

## 🔐 Sécurité

- Protection CSRF
- Validation des données
- Hashage des mots de passe
- Limitation du taux de requêtes
- Logs de sécurité

## 🤝 Contribution

1. Fork le projet
2. Créer une branche (`git checkout -b feature/nouvelle-fonctionnalite`)
3. Commit les changements (`git commit -am 'Ajout nouvelle fonctionnalité'`)
4. Push vers la branche (`git push origin feature/nouvelle-fonctionnalite`)
5. Ouvrir une Pull Request

## 📝 Licence

Ce projet est sous licence MIT. Voir le fichier `LICENSE` pour plus de détails.

## 👨‍💻 Auteur

Développé avec ❤️ pour la gestion moderne des emplois du temps.

## 📞 Support

- 🐛 **Issues** : [GitHub Issues](https://github.com/votre-username/gestion_edt/issues)
- 📧 **Email** : votre-email@exemple.com
- 📖 **Documentation** : Voir le fichier `README_DEPLOIEMENT.md`

---

⭐ N'hésitez pas à donner une étoile si ce projet vous aide !