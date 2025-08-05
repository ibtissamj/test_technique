<?php

namespace App\Controllers;

use App\Services\CurrencyService;
use App\Utils\HttpClient;

/**
 * Contrôleur de conversion de devises
 * 
 * Gère les requêtes de conversion de devises, communique avec le
 * service de taux de change et renvoie les résultats de conversion.
 * 
 * @author Ibtissam Jennate
 */
class CurrencyController extends BaseController
{
    /**
     * Service de conversion de devises
     *
     * @var CurrencyService
     */
    private CurrencyService $service;

    /**
     * Initialise le contrôleur avec le service de conversion
     */
    public function __construct()
    {
        // Injection du client HTTP dans le service
        $this->service = new CurrencyService(new HttpClient());
    }

    /**
     * Point d'entrée API pour la conversion de devises
     * 
     * Traite une requête POST contenant:
     * - amount: Montant à convertir
     * - from_currency: Devise source (code ISO à 3 lettres)
     * - to_currency: Devise cible (code ISO à 3 lettres)
     * 
     * Renvoie le résultat de la conversion au format JSON.
     * 
     * @return void
     */
    public function convert(): void
    {
        // Vérification de la méthode HTTP (seul POST est autorisé)
        if (!$this->isPost()) {
            $this->sendError('Méthode non autorisée', 405);
            return;
        }

        // Récupération et nettoyage des données d'entrée
        $data = sanitizeInput($this->getJsonInput());

        // Validation des champs obligatoires
        $errors = $this->validateRequired($data, ['amount', 'from_currency', 'to_currency']);

        // Validation du montant (doit être un nombre positif dans les limites configurées)
        $amount = $this->validateAmount(
            $data['amount'] ?? null,
            LIMITS['min_conversion_amount'],
            LIMITS['max_conversion_amount']
        );
        if ($amount === false) {
            $errors['amount'] = ERROR_MESSAGES['invalid_amount'];
        }

        // Normalisation des codes de devise (majuscules)
        $from = strtoupper($data['from_currency'] ?? '');
        $to = strtoupper($data['to_currency'] ?? '');

        // Validation des codes de devise (doivent être supportés par l'application)
        if (!isset(SUPPORTED_CURRENCIES[$from])) {
            $errors['from_currency'] = ERROR_MESSAGES['invalid_currency'];
        }
        if (!isset(SUPPORTED_CURRENCIES[$to])) {
            $errors['to_currency'] = ERROR_MESSAGES['invalid_currency'];
        }
        if ($from === $to) {
            $errors['to_currency'] = 'Les devises doivent être différentes.';
        }

        // Si des erreurs sont détectées, renvoyer un message d'erreur
        if (!empty($errors)) {
            $this->sendError('Données invalides', 400, $errors);
            return;
        }

        // Effectuer la conversion via le service et gérer les erreurs potentielles
        try {
            $result = $this->service->convert($amount, $from, $to);
            $this->sendSuccess($result);
        } catch (\Throwable $e) {
            // Journaliser l'erreur pour analyse ultérieure
            logError('Conversion error', ['message' => $e->getMessage()]);
            // Renvoyer un message d'erreur générique à l'utilisateur
            $this->sendError(ERROR_MESSAGES['api_unavailable'], 503);
        }
    }
}