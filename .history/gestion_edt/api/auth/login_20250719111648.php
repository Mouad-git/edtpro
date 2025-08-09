<?php session_start();

require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));

// --- Validation ---
if (!isset($data->email) || !isset($data->mot_de_passe)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email ou mot de passe manquant.']);
    exit;
}

// --- Retrouver l'utilisateur par email ---
$stmt = $pdo->prepare("SELECT id, mot_de_passe FROM utilisateurs WHERE email = ?");
$stmt->execute([$data->email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Vérifier l'utilisateur et le mot de passe ---
if (!$user || !password_verify($data->mot_de_passe, $user['mot_de_passe'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect.']);
    exit;
}

// --- L'utilisateur est authentifié, on récupère son établissement ---
$stmt = $pdo->prepare("SELECT id FROM etablissements WHERE utilisateur_id = ?");
$stmt->execute([$user['id']]);
$etablissement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$etablissement) {
    // Cas rare où un utilisateur n'a pas d'établissement lié
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur: aucun établissement associé à ce compte.']);
    exit;
}

// --- Enregistrer les informations dans la session ---
// C'est le cœur du système de connexion !
$_SESSION['utilisateur_id'] = $user['id'];
$_SESSION['etablissement_id'] = $etablissement['id'];

// --- Envoyer une réponse de succès ---
http_response_code(200);
echo json_encode(['success' => true]);
?>