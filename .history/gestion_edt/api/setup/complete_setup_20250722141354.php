<?php
// api/setup/complete_setup.php (VERSION DE DÉBOGAGE)

// On force l'affichage de toutes les erreurs
ini_set('display_errors', 1);
error_reporting(E_ALL);

// On utilise un header de texte simple pour voir les erreurs brutes
header('Content-Type: text/plain');

// --- TEST 1 : La session est-elle bien démarrée ? ---
session_start();
echo "TEST 1: Session démarrée.\n";
var_dump($_SESSION);
echo "\n";

// --- TEST 2 : Le gardien de session fonctionne-t-il ? ---
if (!isset($_SESSION['utilisateur_id'])) {
    echo "ERREUR TEST 2: 'utilisateur_id' n'est pas dans la session. Le script s'arrête.\n";
    exit;
}
echo "TEST 2: 'utilisateur_id' trouvé : " . $_SESSION['utilisateur_id'] . "\n\n";

// --- TEST 3 : Peut-on se connecter à la BDD et trouver l'établissement ? ---
require_once '../../config/database.php';
$stmt_etab = $pdo->prepare("SELECT id FROM etablissements WHERE utilisateur_id = ? LIMIT 1");
$stmt_etab->execute([$_SESSION['utilisateur_id']]);
$etablissement = $stmt_etab->fetch(PDO::FETCH_ASSOC);

if (!$etablissement) {
    echo "ERREUR TEST 3: Impossible de trouver l'établissement pour l'utilisateur ID " . $_SESSION['utilisateur_id'] . "\n";
    exit;
}
$etablissement_id = $etablissement['id'];
echo "TEST 3: 'etablissement_id' trouvé : " . $etablissement_id . "\n\n";

// --- TEST 4 : Les données du formulaire sont-elles reçues correctement ? ---
$data = json_decode(file_get_contents("php://input"), true);
echo "TEST 4: Données reçues du formulaire :\n";
var_dump($data);
echo "\n";

if (empty($data['formateursData'])) {
     echo "ERREUR TEST 4: Les données des formateurs sont vides !\n";
     exit;
}

// Si on arrive jusqu'ici, tout est bon. On arrête le script avant qu'il ne fasse des bêtises.
echo "FIN DU DÉBOGAGE : Toutes les vérifications initiales ont réussi.";
exit;

?>