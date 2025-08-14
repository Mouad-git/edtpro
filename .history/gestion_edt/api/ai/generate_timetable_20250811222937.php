<?php
// Fichier : api/ai/generate_timetable.php (Version corrigée et avec débogage)

header('Content-Type: application/json');

// --- CONFIGURATION ---
$apiKey = 'AIzaSyCtvJkZ0xp7eSEIVVThjiYQ9HsK84ca_bU';

if (!$apiKey || $apiKey === 'VOTRE_CLE_API_ICI') {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'La clé API Gemini n\'est pas configurée sur le serveur.']);
    exit;
}

// --- RÉCUPÉRATION DES DONNÉES ---
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Données invalides ou manquantes.']);
    exit;
}

// --- CONSTRUCTION DU PROMPT ---
$prompt = "
Tu es un expert en planification d'emplois du temps pour un centre de formation.
Ta tâche est de créer un emploi du temps hebdomadaire complet, en respectant TOUTES les contraintes fournies.

Voici les données de base (formateurs, groupes, modules, espaces disponibles) et les contraintes spécifiques (heures par formateur, indisponibilités, etc.) au format JSON :
" . json_encode($data, JSON_PRETTY_PRINT) . "

### RÈGLES À RESPECTER IMPÉRATIVEMENT :
1.  **Format de sortie** : Ta réponse DOIT être UNIQUEMENT un objet JSON valide, sans aucun texte, commentaire ou explication avant ou après. La structure doit correspondre exactement à `timetableData` : `{\"FORMATEUR_NOM\": {\"Lundi\": {\"S1\": {\"groupe\": \"...\", \"module\": \"...\", \"salle\": \"...\"}, ...}, ...}}`.
2.  **Respect des contraintes** : Chaque contrainte spécifiée dans l'objet `autoGenConstraints` pour un formateur doit être respectée (heures max, sessions TEAMS, espaces autorisés, indisponibilités).
3.  **Unicité** : Un formateur, un groupe ou une salle (sauf 'TEAMS') ne peut être utilisé qu'une seule fois par créneau (jour + séance).
4.  **Cohérence** : Pour un créneau donné, si un formateur enseigne à un groupe, le module doit correspondre à une affectation valide de `appData.affectations`.
5.  **Optimisation** : Essaie de regrouper les cours d'un même formateur sur les mêmes journées pour éviter les jours avec un seul cours. Privilégie les blocs de 5h (deux séances consécutives comme S1-S2 ou S3-S4) lorsque c'est possible et pertinent.
6.  **Complétude** : Tente de placer le maximum de cours possible en respectant les contraintes d'heures pour chaque formateur.

Génère maintenant l'objet JSON complet de l'emploi du temps.
";

// --- APPEL À L'API GEMINI ---
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;

$postData = [
    'contents' => [['parts' => [['text' => $prompt]]]],
    'generationConfig' => ['responseMimeType' => 'application/json']
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

// ======================= CORRECTION POUR LE DÉVELOPPEMENT LOCAL =======================
// Cette ligne désactive la vérification du certificat SSL.
// C'est souvent nécessaire sur localhost (WAMP, XAMPP).
// ATTENTION : Pour un site en production, il faut utiliser une méthode plus sécurisée.
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
// ======================================================================================

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch); // On récupère l'erreur cURL exacte pour le débogage
curl_close($ch);

// --- TRAITEMENT DE LA RÉPONSE ---
if ($curl_error) {
    http_response_code(502); // Bad Gateway, car on n'a pas pu joindre l'API
    echo json_encode([
        'success' => false, 
        'message' => 'Erreur de communication avec l\'API d\'IA.', 
        'details' => "Erreur cURL: " . $curl_error
    ]);
    exit;
}

if ($httpcode != 200) {
    http_response_code($httpcode);
    echo json_encode([
        'success' => false, 
        'message' => 'L\'API d\'IA a retourné une erreur.', 
        'details' => "Code HTTP: $httpcode",
        'response' => json_decode($response)
    ]);
    exit;
}

$responseData = json_decode($response, true);
$timetableJsonString = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? null;

if (!$timetableJsonString) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'L\'IA n\'a pas retourné de contenu valide.', 'details' => $responseData]);
    exit;
}

$finalTimetable = json_decode($timetableJsonString, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'L\'IA a retourné une réponse mal formée (non-JSON).', 'raw' => $timetableJsonString]);
    exit;
}

echo json_encode(['success' => true, 'data' => $finalTimetable]);
?>