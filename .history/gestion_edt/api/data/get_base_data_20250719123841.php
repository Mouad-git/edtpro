<?php
// api/data/get_base_data.php

require_once '../auth/session_check.php'; // Sécurité !
require_once '../../config/database.php'; // Connexion BDD

$etablissement_id = $_SESSION['etablissement_id'];

try {
    // Initialiser la structure de données de sortie
    $appData = [
        'formateurs' => [],
        'groupes' => [],
        'fusionGroupes' => [],
        'espaces' => [],
        'affectations' => []
    ];

    // 1. Récupérer les espaces
    $stmt = $pdo->prepare("SELECT nom_espace FROM espaces WHERE etablissement_id = ? ORDER BY nom_espace");
    $stmt->execute([$etablissement_id]);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $appData['espaces'] = $results;

    // 2. Récupérer les données de base (formateurs, groupes, etc.)
    $stmt = $pdo->prepare("SELECT type_donnee, donnees_json FROM donnees_de_base WHERE etablissement_id = ?");
    $stmt->execute([$etablissement_id]);
    $baseDataResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $formateurs = []; $groupes = []; $fusionGroupes = []; $affectations = [];

// Votre fonction getFormattedName reste la même
function getFormattedName($name) {
    if (!$name || !is_string($name)) return '';
    $words = preg_split('/\s+/', trim($name));
    if (count($words) <= 1) return $name;
    if (count($words) === 2) return $words[1];
    $avantDernier = $words[count($words) - 2];
    $dernier = $words[count($words) - 1];
    return strlen($avantDernier) < 4 ? "$avantDernier $dernier" : $dernier;
}

// On saute la première ligne (en-têtes)
array_shift($rows); 

foreach ($rows as $row) {
    // On vérifie si la ligne n'est pas complètement vide
    if (empty(array_filter($row))) {
        continue;
    }

    $formateurP = getFormattedName($row[20]); // Indice pour la colonne U
    $formateurS = getFormattedName($row[22]); // Indice pour la colonne W
    $groupe = trim($row[8]);                  // Indice pour la colonne I
    $fusionGroupe = trim($row[12]);           // Indice pour la colonne M
    $module = trim($row[16]);                 // Indice pour la colonne Q

    if ($formateurP) $formateurs[] = $formateurP;
    if ($formateurS) $formateurs[] = $formateurS;
    if ($groupe) $groupes[] = $groupe;
    if ($fusionGroupe) $fusionGroupes[] = $fusionGroupe;

    if ($formateurP && $groupe && $module) $affectations[] = ['formateur' => $formateurP, 'groupe' => $groupe, 'module' => $module, 'type' => 'presentiel'];
    if ($formateurS && $fusionGroupe && $module) $affectations[] = ['formateur' => $formateurS, 'groupe' => $fusionGroupe, 'module' => $module, 'type' => 'synchrone'];
}
    
    http_response_code(200);
    echo json_encode(['success' => true, 'data' => $appData]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de la récupération des données de base: ' . $e->getMessage()]);
}
?>