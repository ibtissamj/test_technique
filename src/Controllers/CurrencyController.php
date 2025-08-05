<?php

namespace App\Controllers;

use App\Services\CurrencyService;
use App\Utils\HttpClient;

/**
 * CurrencyController
 * Handles currency conversion requests.
 */
class CurrencyController extends BaseController
{
    private CurrencyService $service;

    public function __construct()
    {
        // Initialize the currency service with an HTTP client
        $this->service = new CurrencyService(new HttpClient());
    }

    /**
     * Converts an amount from one currency to another.
     * Expects POST with JSON: amount, from_currency, to_currency.
     */
    public function convert(): void
    {
        // Only allow POST requests
        if (!$this->isPost()) {
            $this->sendError('Méthode non autorisée', 405);
            return;
        }

        // Sanitize and extract input
        $data = sanitizeInput($this->getJsonInput());

        // Validate required fields
        $errors = $this->validateRequired($data, ['amount', 'from_currency', 'to_currency']);

        // Validate amount
        $amount = $this->validateAmount(
            $data['amount'] ?? null,
            LIMITS['min_conversion_amount'],
            LIMITS['max_conversion_amount']
        );
        if ($amount === false) {
            $errors['amount'] = ERROR_MESSAGES['invalid_amount'];
        }

        // Normalize currency codes
        $from = strtoupper($data['from_currency'] ?? '');
        $to = strtoupper($data['to_currency'] ?? '');

        // Validate currency codes
        if (!isset(SUPPORTED_CURRENCIES[$from])) {
            $errors['from_currency'] = ERROR_MESSAGES['invalid_currency'];
        }
        if (!isset(SUPPORTED_CURRENCIES[$to])) {
            $errors['to_currency'] = ERROR_MESSAGES['invalid_currency'];
        }
        if ($from === $to) {
            $errors['to_currency'] = 'Les devises doivent être différentes.';
        }

        // Return errors if any
        if (!empty($errors)) {
            $this->sendError('Données invalides', 400, $errors);
            return;
        }

        // Perform conversion and handle errors
        try {
            $result = $this->service->convert($amount, $from, $to);
            $this->sendSuccess($result);
        } catch (\Throwable $e) {
            logError('Conversion error', ['message' => $e->getMessage()]);
            $this->sendError(ERROR_MESSAGES['api_unavailable'], 503);
        }
    }
}