<?php

namespace App\Controllers;

use App\Services\LoanCalculatorService;

class LoanController extends BaseController
{
    private LoanCalculatorService $service;

    public function __construct()
    {
        $this->service = new LoanCalculatorService();
    }

    public function calculate(): void
{
    try {
        if (!$this->isPost()) {
            $this->sendError('Méthode non autorisée', 405);
            return;
        }

        if (
            isset($_SERVER['HTTP_ACCEPT']) &&
            stripos($_SERVER['HTTP_ACCEPT'], 'application/json') === false
        ) {
            $this->sendError('Format non supporté', 406);
            return;
        }

        $rawData = file_get_contents('php://input');
        $data = json_decode($rawData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError('JSON invalide : ' . json_last_error_msg(), 400);
            return;
        }

        $errors = $this->validateRequired($data, ['amount', 'rate', 'duration']);

        $amount = $data['amount'] ?? null;
        $rate = $data['rate'] ?? null;
        $duration = $data['duration'] ?? null;

        if (!is_numeric($amount) || $amount < LIMITS['min_loan_amount'] || $amount > LIMITS['max_loan_amount']) {
            $errors['amount'] = ERROR_MESSAGES['invalid_loan_params'];
        }

        if (!is_numeric($rate) || $rate < LIMITS['min_interest_rate'] || $rate > LIMITS['max_interest_rate']) {
            $errors['rate'] = ERROR_MESSAGES['invalid_loan_params'];
        }

        if (!is_numeric($duration) || $duration < LIMITS['min_loan_duration'] || $duration > LIMITS['max_loan_duration'] || (int)$duration != $duration) {
            $errors['duration'] = ERROR_MESSAGES['invalid_loan_params'];
        }

        if (!empty($errors)) {
            $this->sendError('Données invalides', 400, $errors);
            return;
        }

        $result = $this->service->calculateLoan((float)$amount, (float)$rate, (int)$duration);
        $result['timestamp'] = time();
        $this->sendSuccess($result);
    } catch (\Throwable $e) {
        $this->sendError('Erreur interne: ' . $e->getMessage(), 500);
    }
}

}

