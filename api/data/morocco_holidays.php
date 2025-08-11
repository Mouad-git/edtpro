<?php
header('Content-Type: application/json');

// Authorisation CORS (à ajuster selon usage)
header("Access-Control-Allow-Origin: *");

$year = isset($_GET['year']) ? (int)$_GET['year'] : 0;

if ($year !== 2025) {
    http_response_code(501);
    echo json_encode(["error" => "Seule l'année 2025 est prise en charge"]);
    exit;
}

// Jours fériés civils 2025
$civilHolidays2025 = [
    ["title" => "Nouvel An", "date" => "2025-01-01"],
    ["title" => "Anniversaire du Manifeste de l’Indépendance", "date" => "2025-01-11"],
    ["title" => "Nouvel An Amazigh", "date" => "2025-01-14"],
    ["title" => "Fête du Travail", "date" => "2025-05-01"],
    ["title" => "Fête du Trône", "date" => "2025-07-30"],
    ["title" => "Anniversaire de la Récupération de Oued Eddahab", "date" => "2025-08-14"],
    ["title" => "Révolution du Roi et du Peuple", "date" => "2025-08-20"],
    ["title" => "Fête de la Jeunesse", "date" => "2025-08-21"],
    ["title" => "Anniversaire de la Marche Verte", "date" => "2025-11-06"],
    ["title" => "Fête de l’Indépendance", "date" => "2025-11-18"],
];

// Jours fériés religieux estimés 2025
$religiousHolidays2025 = [
    ["title" => "Aïd Al Fitr", "date" => "2025-03-31"],
    ["title" => "Aïd Al Fitr (2e jour)", "date" => "2025-04-01"],
    ["title" => "Aïd Al Adha", "date" => "2025-06-06"],
    ["title" => "Aïd Al Adha (2e jour)", "date" => "2025-06-07"],
    ["title" => "1er Moharram (Nouvel An islamique)", "date" => "2025-06-27"],
    ["title" => "Aïd Al Mawlid (Naissance du Prophète)", "date" => "2025-09-05"],
];

$allHolidays = array_merge($civilHolidays2025, $religiousHolidays2025);

// Retour JSON
echo json_encode($allHolidays);
