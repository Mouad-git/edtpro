<?php
/**
 * Script de Création du Compte Administrateur
 *
 * ATTENTION : À exécuter une seule fois, puis à supprimer de votre serveur.
 * Ce script insère un utilisateur avec le rôle 'admin' dans la base de données.
 */

// Inclure la configuration de la base de données
require_once 'config/database.php';

// --- VOS IDENTIFIANTS ADMIN FIXES ---
$admin_email = 'admin@edtpro.ma';
$admin_password = 'password_admin_1234'; // Choisissez un mot de passe solide
$admin_name = 'Administrateur Principal';
// ------------------------------------

echo "<pre>"; // Pour un affichage plus lisible dans le navigateur

try {
    // 1. Vérifier si l'admin existe déjà
    $stmt = $pdo->prepare("SELECT id FROM utilisateurs WHERE email = ?");
    $stmt->execute([$admin_email]);
    if ($stmt->fetch()) {
        die("ERREUR : Le compte administrateur avec l'email '{$admin_email}' existe déjà. Vous pouvez supprimer ce script.");
    }

    // 2. Hacher le mot de passe (sécurité OBLIGATOIRE)
    $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);

    // 3. Préparer la requête d'insertion
    $sql = "INSERT INTO utilisateurs 
                (nom_complet, email, mot_de_passe, is_verified, is_setup_complete, status, role) 
            VALUES 
                (?, ?, ?, TRUE, TRUE, 'approved', 'admin')";
    
    $stmt = $pdo->prepare($sql);
    
    // 4. Exécuter la requête
    $stmt->execute([
        $admin_name,
        $admin_email,
        $hashed_password
    ]);

    echo "SUCCÈS : Le compte administrateur a été créé avec succès.\n";
    echo "Email: " . $admin_email . "\n";
    echo "Mot de passe: " . $admin_password . "\n\n";
    echo "IMPORTANT : Veuillez maintenant supprimer ce fichier (create_admin.php) de votre serveur.";

} catch (Exception $e) {
    die("ERREUR CRITIQUE : " . $e->getMessage());
}

echo "</pre>";
?>