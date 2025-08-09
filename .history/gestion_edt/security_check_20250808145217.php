<?php
/**
 * Script de vérification de sécurité
 * Ce script teste les protections de sécurité implémentées
 */

require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/config/database.php';

// Désactiver l'affichage des erreurs pour ce script
ini_set('display_errors', 0);
error_reporting(0);

echo "=== VÉRIFICATION DE SÉCURITÉ ===\n\n";

$tests = [];
$passed = 0;
$failed = 0;

// Test 1: Vérification des fonctions de sécurité
echo "1. Test des fonctions de sécurité...\n";
try {
    // Test sanitize_input
    $test_input = "<script>alert('xss')</script>";
    $sanitized = sanitize_input($test_input);
    if ($sanitized !== htmlspecialchars($test_input, ENT_QUOTES, 'UTF-8')) {
        throw new Exception("sanitize_input ne fonctionne pas correctement");
    }
    $tests['sanitize_input'] = true;
    $passed++;
    echo "   ✓ sanitize_input fonctionne\n";
} catch (Exception $e) {
    $tests['sanitize_input'] = false;
    $failed++;
    echo "   ✗ sanitize_input échoue: " . $e->getMessage() . "\n";
}

// Test 2: Vérification de la validation d'email
echo "2. Test de validation d'email...\n";
try {
    $valid_emails = ['test@example.com', 'user.name@domain.co.uk'];
    $invalid_emails = ['invalid-email', 'test@', '@domain.com', 'test@domain'];
    
    foreach ($valid_emails as $email) {
        if (!validate_email($email)) {
            throw new Exception("Email valide rejeté: $email");
        }
    }
    
    foreach ($invalid_emails as $email) {
        if (validate_email($email)) {
            throw new Exception("Email invalide accepté: $email");
        }
    }
    
    $tests['email_validation'] = true;
    $passed++;
    echo "   ✓ Validation d'email fonctionne\n";
} catch (Exception $e) {
    $tests['email_validation'] = false;
    $failed++;
    echo "   ✗ Validation d'email échoue: " . $e->getMessage() . "\n";
}

// Test 3: Vérification de la validation de mot de passe
echo "3. Test de validation de mot de passe...\n";
try {
    $valid_passwords = ['Password123', 'SecurePass1', 'MyPass2023!'];
    $invalid_passwords = ['password', 'PASSWORD', 'Password', 'pass123', '12345678'];
    
    foreach ($valid_passwords as $password) {
        if (!validate_password($password)) {
            throw new Exception("Mot de passe valide rejeté: $password");
        }
    }
    
    foreach ($invalid_passwords as $password) {
        if (validate_password($password)) {
            throw new Exception("Mot de passe invalide accepté: $password");
        }
    }
    
    $tests['password_validation'] = true;
    $passed++;
    echo "   ✓ Validation de mot de passe fonctionne\n";
} catch (Exception $e) {
    $tests['password_validation'] = false;
    $failed++;
    echo "   ✗ Validation de mot de passe échoue: " . $e->getMessage() . "\n";
}

// Test 4: Vérification de la base de données
echo "4. Test de connexion à la base de données...\n";
try {
    $pdo->query('SELECT 1');
    $tests['database_connection'] = true;
    $passed++;
    echo "   ✓ Connexion à la base de données réussie\n";
} catch (Exception $e) {
    $tests['database_connection'] = false;
    $failed++;
    echo "   ✗ Connexion à la base de données échoue: " . $e->getMessage() . "\n";
}

// Test 5: Vérification des fonctions de base de données sécurisées
echo "5. Test des fonctions de base de données sécurisées...\n";
try {
    // Test secure_query
    $stmt = secure_query($pdo, "SELECT 1 as test");
    $result = $stmt->fetch();
    if ($result['test'] != 1) {
        throw new Exception("secure_query ne fonctionne pas");
    }
    
    // Test secure_fetch_one
    $result = secure_fetch_one($pdo, "SELECT 2 as test");
    if ($result['test'] != 2) {
        throw new Exception("secure_fetch_one ne fonctionne pas");
    }
    
    $tests['secure_db_functions'] = true;
    $passed++;
    echo "   ✓ Fonctions de base de données sécurisées fonctionnent\n";
} catch (Exception $e) {
    $tests['secure_db_functions'] = false;
    $failed++;
    echo "   ✗ Fonctions de base de données sécurisées échouent: " . $e->getMessage() . "\n";
}

// Test 6: Vérification du rate limiting
echo "6. Test du rate limiting...\n";
try {
    $test_key = 'test_rate_limit_' . time();
    
    // Première tentative
    if (!check_rate_limit($test_key, 3, 60)) {
        throw new Exception("Rate limit bloque la première tentative");
    }
    
    // Deuxième tentative
    if (!check_rate_limit($test_key, 3, 60)) {
        throw new Exception("Rate limit bloque la deuxième tentative");
    }
    
    // Troisième tentative
    if (!check_rate_limit($test_key, 3, 60)) {
        throw new Exception("Rate limit bloque la troisième tentative");
    }
    
    // Quatrième tentative (devrait être bloquée)
    if (check_rate_limit($test_key, 3, 60)) {
        throw new Exception("Rate limit n'a pas bloqué la quatrième tentative");
    }
    
    $tests['rate_limiting'] = true;
    $passed++;
    echo "   ✓ Rate limiting fonctionne\n";
} catch (Exception $e) {
    $tests['rate_limiting'] = false;
    $failed++;
    echo "   ✗ Rate limiting échoue: " . $e->getMessage() . "\n";
}

// Test 7: Vérification des tokens CSRF
echo "7. Test des tokens CSRF...\n";
try {
    session_start();
    
    $token1 = generate_csrf_token();
    $token2 = generate_csrf_token();
    
    if ($token1 !== $token2) {
        throw new Exception("Tokens CSRF ne sont pas cohérents");
    }
    
    if (!validate_csrf_token($token1)) {
        throw new Exception("Validation CSRF échoue pour un token valide");
    }
    
    if (validate_csrf_token('invalid_token')) {
        throw new Exception("Validation CSRF accepte un token invalide");
    }
    
    $tests['csrf_tokens'] = true;
    $passed++;
    echo "   ✓ Tokens CSRF fonctionnent\n";
} catch (Exception $e) {
    $tests['csrf_tokens'] = false;
    $failed++;
    echo "   ✗ Tokens CSRF échouent: " . $e->getMessage() . "\n";
}

// Test 8: Vérification des logs
echo "8. Test des logs de sécurité...\n";
try {
    $test_message = "Test de log " . time();
    secure_log($test_message, 'TEST');
    
    $log_file = dirname(__DIR__) . '/logs/security.log';
    if (!file_exists($log_file)) {
        throw new Exception("Fichier de log de sécurité n'existe pas");
    }
    
    $log_content = file_get_contents($log_file);
    if (strpos($log_content, $test_message) === false) {
        throw new Exception("Message de test non trouvé dans les logs");
    }
    
    $tests['security_logs'] = true;
    $passed++;
    echo "   ✓ Logs de sécurité fonctionnent\n";
} catch (Exception $e) {
    $tests['security_logs'] = false;
    $failed++;
    echo "   ✗ Logs de sécurité échouent: " . $e->getMessage() . "\n";
}

// Test 9: Vérification des headers de sécurité
echo "9. Test des headers de sécurité...\n";
try {
    $headers = headers_list();
    $required_headers = [
        'X-Content-Type-Options',
        'X-Frame-Options',
        'X-XSS-Protection',
        'Referrer-Policy'
    ];
    
    foreach ($required_headers as $header) {
        $found = false;
        foreach ($headers as $sent_header) {
            if (stripos($sent_header, $header) === 0) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new Exception("Header de sécurité manquant: $header");
        }
    }
    
    $tests['security_headers'] = true;
    $passed++;
    echo "   ✓ Headers de sécurité sont présents\n";
} catch (Exception $e) {
    $tests['security_headers'] = false;
    $failed++;
    echo "   ✗ Headers de sécurité échouent: " . $e->getMessage() . "\n";
}

// Test 10: Vérification de la validation des entrées
echo "10. Test de validation des entrées...\n";
try {
    // Test validate_id
    if (validate_id(123) !== 123) {
        throw new Exception("validate_id échoue pour un ID valide");
    }
    if (validate_id(-1) !== false) {
        throw new Exception("validate_id accepte un ID négatif");
    }
    if (validate_id('abc') !== false) {
        throw new Exception("validate_id accepte une chaîne non numérique");
    }
    
    // Test validate_string
    if (validate_string('test', 10) !== 'test') {
        throw new Exception("validate_string échoue pour une chaîne valide");
    }
    if (validate_string('test', 2) !== false) {
        throw new Exception("validate_string accepte une chaîne trop longue");
    }
    
    $tests['input_validation'] = true;
    $passed++;
    echo "   ✓ Validation des entrées fonctionne\n";
} catch (Exception $e) {
    $tests['input_validation'] = false;
    $failed++;
    echo "   ✗ Validation des entrées échoue: " . $e->getMessage() . "\n";
}

// Résumé des tests
echo "\n=== RÉSUMÉ DES TESTS ===\n";
echo "Tests réussis: $passed\n";
echo "Tests échoués: $failed\n";
echo "Total: " . ($passed + $failed) . "\n\n";

if ($failed > 0) {
    echo "⚠️  ATTENTION: Certains tests de sécurité ont échoué!\n";
    echo "Veuillez corriger les problèmes avant de déployer en production.\n\n";
    
    echo "Tests échoués:\n";
    foreach ($tests as $test_name => $result) {
        if (!$result) {
            echo "- $test_name\n";
        }
    }
} else {
    echo "✅ Tous les tests de sécurité ont réussi!\n";
    echo "Votre application est prête pour la production.\n";
}

echo "\n=== RECOMMANDATIONS ===\n";
echo "1. Configurez les variables d'environnement pour la production\n";
echo "2. Activez HTTPS sur votre serveur\n";
echo "3. Configurez les sauvegardes de base de données\n";
echo "4. Surveillez régulièrement les logs de sécurité\n";
echo "5. Effectuez des tests de sécurité périodiques\n";

// Nettoyage
if (isset($pdo)) {
    $pdo = null;
}
?> 