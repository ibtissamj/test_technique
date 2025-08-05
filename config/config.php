<?php

/**
 * Configuration de l'application
 *
 * Ce fichier contient toutes les constantes et configurations
 * nécessaires au bon fonctionnement de l'application
 */

// Empêcher l'accès direct
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Configuration de l'environnement
define('ENVIRONMENT', 'development'); // development | production

// Configuration d'affichage des erreurs
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Configuration de la timezone
date_default_timezone_set('Europe/Paris');

// Configuration des APIs externes
define('API_CONFIG', [
    'exchange_rates' => [
        'base_url' => 'https://api.exchangeratesapi.io/v1',
        'endpoints' => [
            'latest' => 'latest',
            'convert' => 'convert'
        ],
        // // Pas de clé API requise pour ExchangeRates.host
        'api_key' => getenv('API_KEY')

    ],
    'openiban' => [
        'base_url' => 'https://openiban.com/',
        'endpoints' => [
            'validate' => 'validate/{iban}?getBIC=true'
        ],
        // Pas de clé API requise
        'api_key' => null
    ]
]);

// Configuration de la base de données (optionnel)
define('DB_CONFIG', [
    'host' => 'db',
    'port' => 3306,
    'database' => 'test_cm',
    'username' => 'test_user',
    'password' => 'test_pass',
    'charset' => 'utf8mb4'
]);

// Configuration du cache (optionnel)
define('CACHE_CONFIG', [
    'enabled' => true,
    'ttl' => 3600, // 1 heure en secondes
    'path' => BASE_PATH . '/cache/'
]);

// Liste des devises supportées
define('SUPPORTED_CURRENCIES', [
    'EUR' => 'Euro',
    'USD' => 'Dollar américain',
    'GBP' => 'Livre sterling',
    'CHF' => 'Franc suisse',
    'JPY' => 'Yen japonais',
    'CAD' => 'Dollar canadien',
    'AUD' => 'Dollar australien',
    'CNY' => 'Yuan chinois'
]);

// Configuration des limites
define('LIMITS', [
    'max_conversion_amount' => 1000000,
    'min_conversion_amount' => 0.01,
    'max_loan_amount' => 10000000,
    'min_loan_amount' => 1000,
    'max_loan_duration' => 30, // années
    'min_loan_duration' => 1,
    'max_interest_rate' => 20, // %
    'min_interest_rate' => 0.01
]);

// Messages d'erreur standards
define('ERROR_MESSAGES', [
    'api_unavailable' => "Le service est temporairement indisponible. Veuillez réessayer plus tard.",
    'invalid_amount' => "Le montant saisi est invalide.",
    'invalid_currency' => "La devise sélectionnée n'est pas supportée.",
    'invalid_iban' => "Le numéro IBAN saisi est invalide.",
    'invalid_loan_params' => "Les paramètres du prêt sont invalides.",
    'rate_limit' => "Trop de requêtes. Veuillez patienter avant de réessayer.",
    'unknown_error' => "Une erreur inattendue s'est produite."
]);

// Fonction d'autoloading simple
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = BASE_PATH . '/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Fonction utilitaire pour les réponses JSON
function jsonResponse($data, $statusCode = 200)
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Fonction de validation des entrées
function sanitizeInput($input)
{
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Fonction de logging simple
function logError($message, $context = [])
{
    if (ENVIRONMENT === 'development') {
        $logFile = BASE_PATH . '/logs/error.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $logEntry = sprintf(
            "[%s] %s %s\n",
            date('Y-m-d H:i:s'),
            $message,
            !empty($context) ? json_encode($context) : ''
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
