<?php

require_once '.../.../includes/functions.php';
/**
 * Fichier de configuration de la base de données sécurisé.
 * Ce fichier établit la connexion à la base de données MySQL en utilisant PDO.
 * Il doit être inclus dans tous les scripts de l'API qui ont besoin d'interagir avec la BDD.
 */

// --- CONFIGURATION SÉCURISÉE ---
// Utilisation de variables d'environnement ou de valeurs par défaut sécurisées

// Hôte de la base de données
$host = $_ENV['DB_HOST'] ?? 'localhost';

// Nom de la base de données
$dbname = $_ENV['DB_NAME'] ?? 'gestion_edt';

// Nom d'utilisateur pour se connecter à la base de données
$username = $_ENV['DB_USER'] ?? 'root';

// Mot de passe pour l'utilisateur
$password = $_ENV['DB_PASSWORD'] ?? '';

// --- VALIDATION DES PARAMÈTRES ---
if (empty($host) || empty($dbname) || empty($username)) {
    secure_log('Configuration de base de données manquante', 'ERROR');
    die("Erreur de configuration de la base de données.");
}

// --- CONNEXION SÉCURISÉE ---
try {
    // DSN avec options de sécurité
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    // Options PDO sécurisées
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // Force l'utilisation de prepared statements
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::ATTR_PERSISTENT => false, // Évite les connexions persistantes
    ];
    
    // Création de l'instance PDO avec options sécurisées
    $pdo = new PDO($dsn, $username, $password, $options);
    
    // Test de la connexion
    $pdo->query('SELECT 1');
    
    secure_log('Connexion à la base de données réussie', 'INFO');
    
} catch (PDOException $e) {
    // Journalisation de l'erreur sans exposer les détails
    secure_log('Erreur de connexion BDD: ' . $e->getMessage(), 'ERROR');
    
    // Message d'erreur générique pour l'utilisateur
    http_response_code(500);
    die("Erreur de connexion à la base de données. Veuillez contacter l'administrateur.");
}

// --- FONCTIONS DE SÉCURITÉ POUR LA BDD ---

/**
 * Exécute une requête préparée de manière sécurisée
 */
function secure_query($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        secure_log('Erreur SQL: ' . $e->getMessage() . ' - Query: ' . $sql, 'ERROR');
        // DANS database.php, fonction secure_query
// MODIFIEZ CETTE LIGNE
throw new Exception('Erreur de base de données : ' . $e->getMessage());
    }
}

/**
 * Récupère une seule ligne de manière sécurisée
 */
function secure_fetch_one($pdo, $sql, $params = []) {
    $stmt = secure_query($pdo, $sql, $params);
    return $stmt->fetch();
}

/**
 * Récupère toutes les lignes de manière sécurisée
 */
function secure_fetch_all($pdo, $sql, $params = []) {
    $stmt = secure_query($pdo, $sql, $params);
    return $stmt->fetchAll();
}

/**
 * Insère une ligne de manière sécurisée
 */
function secure_insert($pdo, $table, $data) {
    $columns = array_keys($data);
    $placeholders = ':' . implode(', :', $columns);
    $sql = "INSERT INTO $table (" . implode(', ', $columns) . ") VALUES ($placeholders)";
    
    $stmt = secure_query($pdo, $sql, $data);
    return $pdo->lastInsertId();
}

/**
 * Met à jour une ligne de manière sécurisée
 */
function secure_update($pdo, $table, $data, $where_conditions) {
    // Construction de la partie SET (pas de changement ici)
    $set_parts = [];
    foreach (array_keys($data) as $column) {
        $set_parts[] = "$column = :$column";
    }
    
    // Construction de la partie WHERE avec des marqueurs nommés
    $where_parts = [];
    foreach (array_keys($where_conditions) as $column) {
        // On ajoute un préfixe "where_" pour éviter les conflits de noms avec la partie SET
        $where_parts[] = "$column = :where_$column";
    }
    
    $sql = "UPDATE $table SET " . implode(', ', $set_parts) . " WHERE " . implode(' AND ', $where_parts);
    
    // Préparation des paramètres pour la clause WHERE
    $where_params = [];
    foreach ($where_conditions as $key => $value) {
        $where_params[":where_$key"] = $value;
    }
    
    // Fusion de tous les paramètres
    $params = array_merge($data, $where_params);
    
    return secure_query($pdo, $sql, $params);
}

/**
 * Supprime des lignes de manière sécurisée
 */
function secure_delete($pdo, $table, $where, $params = []) {
    $sql = "DELETE FROM $table WHERE $where";
    return secure_query($pdo, $sql, $params);
}

// --- VALIDATION DES DONNÉES ---

/**
 * Valide et nettoie un identifiant numérique
 */
function validate_id($id) {
    return is_numeric($id) && $id > 0 ? (int)$id : false;
}

/**
 * Valide et nettoie une chaîne de caractères
 */
function validate_string($string, $max_length = 255) {
    $string = sanitize_input($string);
    return strlen($string) <= $max_length ? $string : false;
}

/**
 * Valide et nettoie un email
 */
function validate_email_db($email) {
    return validate_email($email) ? $email : false;
}

/**
 * Valide et nettoie un mot de passe pour la base de données
 */
function validate_password_db($password) {
    return validate_password($password) ? $password : false;
}

// --- PROTECTION CONTRE LES INJECTIONS ---

/**
 * Échappe les caractères spéciaux pour les requêtes LIKE
 */
function escape_like($string) {
    return str_replace(['%', '_'], ['\\%', '\\_'], $string);
}

/**
 * Valide un nom de table pour éviter les injections SQL
 */
function validate_table_name($table) {
    return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table) ? $table : false;
}

/**
 * Valide un nom de colonne pour éviter les injections SQL
 */
function validate_column_name($column) {
    return preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column) ? $column : false;
}

secure_log('Configuration de base de données chargée avec succès', 'INFO');
?>