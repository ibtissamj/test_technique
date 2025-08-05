<?php

namespace App\Controllers;

use App\Services\LoanCalculatorService;

/**
 * Contrôleur pour les calculs de prêts immobiliers
 * 
 * Gère les requêtes de calcul de prêt, valide les entrées 
 * utilisateur et renvoie les informations calculées.
 * 
 * @author Ibtissam Jennate
 */
class LoanController extends BaseController
{
    /**
     * Service de calcul de prêt
     * 
     * @var LoanCalculatorService
     */
    private LoanCalculatorService $service;

    /**
     * Initialise le contrôleur avec le service de calcul de prêt
     */
    public function __construct()
    {
        $this->service = new LoanCalculatorService();
    }

    /**
     * Point d'entrée API pour le calcul des mensualités de prêt
     * 
     * Traite une requête POST contenant:
     * - amount: Montant emprunté (€)
     * - rate: Taux d'intérêt annuel (%)
     * - duration: Durée du prêt en années
     * 
     * Renvoie les détails du prêt calculé au format JSON.
     * 
     * @return void
     */
    public function calculate(): void
    {
        try {
            // Vérification de la méthode HTTP
            if (!$this->isPost()) {
                $this->sendError('Méthode non autorisée', 405);
                return;
            }

            // Vérification du format de réponse attendu
            if (
                isset($_SERVER['HTTP_ACCEPT']) &&
                stripos($_SERVER['HTTP_ACCEPT'], 'application/json') === false
            ) {
                $this->sendError('Format non supporté', 406);
                return;
            }

            // Récupération et décodage des données JSON
            $rawData = file_get_contents('php://input');
            $data = json_decode($rawData, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->sendError('JSON invalide : ' . json_last_error_msg(), 400);
                return;
            }

            // Validation des champs requis
            $errors = $this->validateRequired($data, ['amount', 'rate', 'duration']);

            // Extraction et validation des paramètres
            $amount = $data['amount'] ?? null;
            $rate = $data['rate'] ?? null;
            $duration = $data['duration'] ?? null;

            // Validation du montant
            if (!is_numeric($amount) || $amount < LIMITS['min_loan_amount'] || $amount > LIMITS['max_loan_amount']) {
                $errors['amount'] = ERROR_MESSAGES['invalid_loan_params'];
            }

            // Validation du taux d'intérêt
            if (!is_numeric($rate) || $rate < LIMITS['min_interest_rate'] || $rate > LIMITS['max_interest_rate']) {
                $errors['rate'] = ERROR_MESSAGES['invalid_loan_params'];
            }

            // Validation de la durée
            if (!is_numeric($duration) || $duration < LIMITS['min_loan_duration'] || $duration > LIMITS['max_loan_duration'] || (int)$duration != $duration) {
                $errors['duration'] = ERROR_MESSAGES['invalid_loan_params'];
            }

            // Renvoyer les erreurs si présentes
            if (!empty($errors)) {
                $this->sendError('Données invalides', 400, $errors);
                return;
            }

            // Calcul du prêt via le service
            $result = $this->service->calculateLoan((float)$amount, (float)$rate, (int)$duration);
            $result['timestamp'] = time();
            
            // Renvoyer le résultat
            $this->sendSuccess($result);
            
        } catch (\Throwable $e) {
            // Gestion des exceptions non prévues
            $this->sendError('Erreur interne: ' . $e->getMessage(), 500);
        }
    }
}

