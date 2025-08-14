<?php
// api/profile/update_formateurs.php
require_once '../auth/session_check.php';
require_once '../../config/database.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);
$etablissement_id = $_SESSION['etablissement_id'];

if (!isset($data['formateurs']) || !is_array($data['formateurs'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données des formateurs invalides.']);
    exit;
}

try {
    // Mettre à jour `formateurs_details` formateur par formateur
    $upsert = $pdo->prepare(
        "INSERT INTO formateurs_details (etablissement_id, nom_formateur, matricule, email, masse_horaire_statutaire)
         VALUES (:etab_id, :nom, :matricule, :email, :masse)
         ON DUPLICATE KEY UPDATE matricule = VALUES(matricule), email = VALUES(email), masse_horaire_statutaire = VALUES(masse_horaire_statutaire)"
    );

    $names = [];
    foreach ($data['formateurs'] as $f) {
        $nom = isset($f['nom']) ? trim($f['nom']) : '';
        if ($nom === '') { // ignorer les entrées vides
            continue;
        }
        $matricule = isset($f['matricule']) ? trim((string)$f['matricule']) : '';
        $email = isset($f['email']) ? trim($f['email']) : '';
        $masse = isset($f['masse_horaire']) ? (int)$f['masse_horaire'] : 0;
        $upsert->execute([
            ':etab_id' => $etablissement_id,
            ':nom' => $nom,
            ':matricule' => $matricule,
            ':email' => $email,
            ':masse' => $masse,
        ]);
        $names[] = $nom;
    }

    // Maintenir la liste simple des noms dans `donnees_de_base` (utile ailleurs)
    if (!empty($names)) {
        $pdo->prepare("INSERT INTO donnees_de_base (etablissement_id, type_donnee, donnees_json) VALUES (?, 'formateur', ?) ON DUPLICATE KEY UPDATE donnees_json = VALUES(donnees_json)")
            ->execute([$etablissement_id, json_encode(array_values(array_unique($names)))]);
    }

    echo json_encode(['success' => true, 'message' => "Formateurs mis à jour."]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}
?>