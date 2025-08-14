<?php
header('Content-Type: application/json');

// Autoriser uniquement POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// Lire l'entrée JSON
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['slots']) || !is_array($input['slots'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Payload invalide']);
    exit;
}

// Charger les poids du modèle (fallback par défaut si absent)
$weightsPath = __DIR__ . '/model_weights.json';
$defaultWeights = [
    'bias' => 0.0,
    'adjacencyPrev' => 1.0,
    'adjacencyNext' => 1.0,
    'sameDayHasOther' => 0.3,
    'groupBalance' => 0.5,
    'isTeams' => -0.2,
    'withinTeamsQuota' => 0.4,
    'hoursLoad' => -0.3
];

if (file_exists($weightsPath)) {
    $json = json_decode(file_get_contents($weightsPath), true);
    if (is_array($json)) {
        // Fusionner pour garantir toutes les clés nécessaires
        $weights = array_merge($defaultWeights, $json);
    } else {
        $weights = $defaultWeights;
    }
} else {
    $weights = $defaultWeights;
}

// Fonction score linéaire + optionnellement une sigmoïde légère si désiré
function linear_score(array $features, array $weights): float {
    $score = $weights['bias'] ?? 0.0;
    foreach ($features as $name => $value) {
        if (!is_numeric($value)) continue;
        $w = $weights[$name] ?? 0.0;
        $score += $w * $value;
    }
    return $score;
}

$scored = [];
foreach ($input['slots'] as $entry) {
    // Chaque entrée attendue: { slot: {...}, features: {...} }
    $slot = isset($entry['slot']) ? $entry['slot'] : null;
    $features = isset($entry['features']) ? $entry['features'] : [];
    if (!$slot || !is_array($slot) || !is_array($features)) {
        continue;
    }
    $score = linear_score($features, $weights);
    $scored[] = [
        'slot' => $slot,
        'score' => $score
    ];
}

// Trier par score décroissant
usort($scored, function ($a, $b) {
    if ($a['score'] === $b['score']) return 0;
    return ($a['score'] < $b['score']) ? 1 : -1;
});

// Ne renvoyer que les slots (et éventuellement le score pour debug)
$result = [
    'slots' => array_map(function ($e) { return $e['slot']; }, $scored),
    'debug' => [ 'count' => count($scored) ]
];

echo json_encode($result);
?>


