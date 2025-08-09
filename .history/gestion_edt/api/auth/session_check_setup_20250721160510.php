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
?>