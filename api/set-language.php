<?php
/* ============================================================
   THE ARCHIVES OF CLAN LAR — Set Language API
   POST with lang=en|ru → updates session + user record
   ============================================================ */
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

// Accept both form data and JSON body
$lang = $_POST['lang'] ?? '';
if (empty($lang)) {
    $jsonBody = json_decode(file_get_contents('php://input'), true);
    $lang = $jsonBody['lang'] ?? '';
}

if (!in_array($lang, ['en', 'ru'])) {
    jsonResponse(['error' => 'Invalid language. Supported: en, ru'], 400);
}

setLang($lang);

jsonResponse([
    'success'  => true,
    'language' => $lang,
]);
