<?php
//require_once '../../config/security.php'; // Toujours en premier !
/**
 * Fichier de configuration de la base de données.
 * Ce fichier établit la connexion à la base de données MySQL en utilisant PDO.
 * Il doit être inclus dans tous les scripts de l'API qui ont besoin d'interagir avec la BDD.
 */

// --- PERSONNALISEZ CES VARIABLES SELON VOTRE ENVIRONNEMENT ---

// Hôte de la base de données. Pour un serveur local (XAMPP, WAMP, MAMP), c'est presque toujours 'localhost'.
$host = 'localhost';

// Nom de la base de données que vous avez créée dans phpMyAdmin.
$dbname = 'gestion_edt';

// Nom d'utilisateur pour se connecter à la base de données.
// Par défaut pour XAMPP/WAMP, c'est 'root'.
$username = 'root';

// Mot de passe pour l'utilisateur.
// Par défaut pour XAMPP/WAMP, il est vide.
$password = '';

// --- FIN DE LA ZONE DE PERSONNALISATION ---


try {
    // Tentative de connexion à la base de données.
    // DSN (Data Source Name) spécifie l'hôte, le nom de la base de données et l'encodage des caractères.
    // Utiliser 'utf8mb4' est une bonne pratique pour une compatibilité complète des caractères (y compris les emojis).
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    // Création de l'instance PDO (PHP Data Objects). C'est notre objet de connexion.
    $pdo = new PDO($dsn, $username, $password);
    
    // Configuration des options de PDO pour une meilleure gestion des erreurs.
    // PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION:
    // Cette ligne est très importante. Elle dit à PDO de "lancer une exception" (une erreur PHP)
    // chaque fois qu'une requête SQL échoue. Cela nous permet d'attraper ces erreurs avec le bloc 'catch'.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

} catch (PDOException $e) {
    // Si la connexion échoue (mauvais mot de passe, base de données inexistante, etc.),
    // le code dans le bloc 'catch' est exécuté.
    
    // On arrête immédiatement l'exécution du script.
    // On affiche un message d'erreur générique pour ne pas révéler de détails sensibles sur un serveur en production.
    die("Erreur de connexion à la base de données. Veuillez vérifier votre configuration. Message: " . $e->getMessage());
}

// Si le script arrive jusqu'ici sans erreur, la variable $pdo contient une connexion valide à la base de données
// et peut être utilisée par les autres scripts qui incluront ce fichier.
?>