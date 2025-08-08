# Guide de Sécurité - Gestion EDT

## Vue d'ensemble

Ce document décrit les mesures de sécurité implémentées dans l'application de gestion d'emplois du temps.

## Mesures de Sécurité Implémentées

### 1. Protection des Sessions

- **Régénération d'ID de session** : L'ID de session est régénéré toutes les 5 minutes pour prévenir la fixation de session
- **Vérification d'IP** : La session est invalidée si l'IP change
- **Vérification d'User-Agent** : La session est invalidée si l'User-Agent change
- **Expiration automatique** : Les sessions expirent après 1 heure d'inactivité
- **Cookies sécurisés** : HttpOnly, Secure, SameSite=Strict

### 2. Protection contre les Attaques Web

#### Headers de Sécurité
- `X-Content-Type-Options: nosniff` - Empêche le MIME sniffing
- `X-Frame-Options: DENY` - Empêche le clickjacking
- `X-XSS-Protection: 1; mode=block` - Protection XSS du navigateur
- `Referrer-Policy: strict-origin-when-cross-origin` - Contrôle des référents
- `Content-Security-Policy` - Politique de sécurité du contenu

#### Protection CSRF
- Génération de tokens CSRF uniques
- Validation des tokens sur toutes les requêtes POST
- Régénération des tokens après chaque utilisation

### 3. Rate Limiting

- **Connexion** : 5 tentatives max en 5 minutes
- **Inscription** : 3 tentatives max par heure
- **Vérification** : 10 tentatives max en 15 minutes
- Stockage des tentatives par IP

### 4. Validation et Nettoyage des Données

#### Validation des Entrées
- Validation stricte des emails avec regex
- Validation des mots de passe (8+ caractères, majuscule, minuscule, chiffre)
- Validation des chaînes de caractères avec limites de longueur
- Validation des identifiants numériques

#### Nettoyage des Données
- Fonction `sanitize_input()` pour nettoyer toutes les entrées
- Protection contre les injections XSS
- Échappement des caractères spéciaux

### 5. Sécurité de la Base de Données

#### Protection contre les Injections SQL
- Utilisation exclusive de requêtes préparées
- Fonctions `secure_query()`, `secure_fetch_one()`, etc.
- Validation des noms de tables et colonnes
- Échappement des caractères spéciaux pour LIKE

#### Configuration PDO Sécurisée
- `PDO::ATTR_EMULATE_PREPARES => false`
- `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`
- Connexions non persistantes

### 6. Gestion des Erreurs et Logs

#### Journalisation Sécurisée
- Logs détaillés avec IP, User-Agent, timestamp
- Rotation automatique des logs (30 jours)
- Nettoyage automatique des anciens logs
- Pas d'exposition d'informations sensibles dans les logs

#### Gestion des Erreurs
- Messages d'erreur génériques en production
- Journalisation des erreurs sans exposition
- Redirection des erreurs vers des pages sécurisées

### 7. Protection des Fichiers

#### .htaccess Sécurisé
- Désactivation de l'indexation des répertoires
- Protection des fichiers sensibles (config, logs, .sql)
- Blocage des exécutions CGI
- Headers de sécurité pour tous les fichiers

#### Structure Sécurisée
- Séparation des fichiers publics et privés
- Protection du dossier `config/`
- Protection du dossier `logs/`
- Protection des fichiers de données

### 8. Authentification et Autorisation

#### Connexion Sécurisée
- Hachage des mots de passe avec `password_hash()`
- Protection contre les attaques par timing avec `hash_equals()`
- Validation stricte des identifiants
- Gestion des rôles et permissions

#### Inscription Sécurisée
- Validation complète des données
- Vérification des emails existants
- Envoi d'emails de vérification sécurisés
- Protection contre les inscriptions multiples

### 9. Configuration d'Environnement

#### Variables d'Environnement
- Séparation des configurations par environnement
- Variables sensibles externalisées
- Validation des variables critiques
- Configuration adaptée selon l'environnement

#### Environnements
- **Development** : Affichage des erreurs, logs détaillés
- **Staging** : Configuration intermédiaire
- **Production** : Sécurité maximale, pas d'affichage d'erreurs

### 10. Protection contre les Attaques Spécifiques

#### Attaques par Force Brute
- Rate limiting sur les formulaires sensibles
- Délais progressifs
- Journalisation des tentatives échouées

#### Attaques XSS
- Nettoyage de toutes les entrées utilisateur
- Headers de sécurité appropriés
- Validation côté serveur

#### Attaques CSRF
- Tokens CSRF sur tous les formulaires
- Validation stricte des tokens
- Régénération des tokens

#### Attaques par Injection
- Requêtes préparées exclusivement
- Validation des types de données
- Échappement des caractères spéciaux

## Recommandations de Déploiement

### 1. Configuration Serveur

```apache
# Dans le .htaccess principal
Options -Indexes -ExecCGI -FollowSymLinks
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

### 2. Variables d'Environnement

Créer un fichier `.env` avec :
```env
APP_ENV=production
DB_HOST=localhost
DB_NAME=gestion_edt
DB_USER=root
DB_PASSWORD=votre_mot_de_passe_securise
SMTP_HOST=smtp.gmail.com
SMTP_USER=votre_email@gmail.com
SMTP_PASS=votre_mot_de_passe_app
```

### 3. Permissions des Fichiers

```bash
# Dossiers de logs avec permissions restreintes
chmod 755 logs/
chmod 644 logs/*.log

# Fichiers de configuration
chmod 644 config/*.php

# Fichiers publics
chmod 644 public/*.html
chmod 644 public/assets/*
```

### 4. Base de Données

- Utiliser un utilisateur MySQL dédié avec permissions limitées
- Changer le mot de passe par défaut de root
- Activer les logs MySQL
- Configurer les sauvegardes automatiques

### 5. SSL/TLS

- Forcer HTTPS en production
- Configurer les certificats SSL
- Rediriger HTTP vers HTTPS
- Configurer HSTS

## Maintenance et Surveillance

### 1. Surveillance des Logs

- Vérifier régulièrement les logs de sécurité
- Surveiller les tentatives de connexion échouées
- Surveiller les tentatives d'accès aux fichiers protégés

### 2. Mises à Jour

- Maintenir PHP à jour
- Maintenir les dépendances Composer à jour
- Maintenir le serveur web à jour

### 3. Tests de Sécurité

- Tester régulièrement les protections CSRF
- Tester les validations d'entrée
- Tester les protections XSS
- Tester les protections SQL injection

## Contact Sécurité

Pour signaler une vulnérabilité de sécurité :
- Email : security@votre-domaine.com
- Réponse sous 24h
- Coordinated disclosure encouragé

---

**Dernière mise à jour** : $(date)
**Version** : 1.0 