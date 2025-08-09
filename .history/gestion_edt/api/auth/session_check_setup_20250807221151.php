<?php

/**
 * Vérificateur de Session pour la phase de Configuration (Setup)
 *
 * Ce gardien est moins strict. Il vérifie uniquement que l'utilisateur est
 * authentifié (a un utilisateur_id), mais n'exige PAS qu'un établissement
 * ait déjà été sélectionné.
 */

session_start();

if (!isset($_SESSION['utilisateur_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé. Vous devez être connecté pour accéder à la configuration.']);
    exit;
}

// NOTE IMPORTANTE:
// Pour que le reste du script puisse fonctionner, nous devons tout de même
// définir la variable $etablissement_id. On va la chercher maintenant.
require_once '../../config/database.php';
$stmt_etab = $pdo->prepare("SELECT id FROM etablissements WHERE utilisateur_id = ? LIMIT 1");
$stmt_etab->execute([$_SESSION['utilisateur_id']]);
$etablissement = $stmt_etab->fetch(PDO::FETCH_ASSOC);

if (!$etablissement) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur critique: Utilisateur sans établissement trouvé.']);
    exit;
}

// On définit la variable pour que les scripts qui incluent ce fichier puissent l'utiliser.
$etablissement_id = $etablissement['id'];
?>