<?php


session_start();
require_once '../config/config.php';

// Routing API
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($uri === '/' || $uri === '') {
    require_once __DIR__ . '/../src/Views/home.php';
    exit;
}
if (strpos($uri, '/api/') === 0) {
    header('Content-Type: application/json; charset=utf-8');

    switch ($uri) {
        case '/api/convert':
            $controller = new \App\Controllers\CurrencyController();
            $controller->convert();
            break;

        case '/api/iban':
            $controller = new \App\Controllers\IbanController();
            $controller->validate();
            break;

        case '/api/loan':
            $controller = new \App\Controllers\LoanController();
            $controller->calculate();
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Endpoint non trouvé'], 404);
            break;
    }
    exit;
}
http_response_code(404);
echo '404 - Page non trouvée';
?>
