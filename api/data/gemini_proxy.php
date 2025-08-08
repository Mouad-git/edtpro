<?php
header('Content-Type: application/json');

// 1. Récupérez votre clé API depuis une variable d'environnement ou un fichier de configuration sécurisé.
// NE JAMAIS la laisser directement dans le code.
// Pour cet exemple, nous la mettons ici, mais déplacez-la !
$apiKey = getenv('GEMINI_API_KEY'); // Méthode recommandée
if (!$apiKey) {
    $apiKey = 'AIzaSyCtvJkZ0xp7eSEIVVThjiYQ9HsK84ca_bU'; // Méthode de secours (moins sécurisée)
}

// 2. Vérifiez que la requête est bien de type POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Méthode non autorisée.']);
    exit;
}

// 3. Récupérez le prompt envoyé par le client
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['prompt']) || empty($input['prompt'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Prompt manquant.']);
    exit;
}

$prompt = $input['prompt'];

// 4. Préparez et exécutez la requête cURL vers l'API Google
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;

$data = [
    'contents' => [[
        'parts' => [[
            'text' => $prompt
        ]]
    ]]
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Important pour la sécurité

$response = curl_exec($ch);
curl_close($ch);

// 5. Renvoyez la réponse de Google directement au client
echo $response;