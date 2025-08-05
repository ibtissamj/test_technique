<?php

namespace App\Controllers;

use App\Services\IbanService;
use App\Utils\HttpClient;

/**
 * Contrôleur pour la validation des IBAN
 * 
 * Gère les requêtes de validation d'IBAN, communique avec
 * le service IbanService et renvoie les informations bancaires.
 * 
 * @author Ibtissam Jennate
 */
class IbanController extends BaseController
{
    /**
     * Service de validation d'IBAN
     * 
     * @var IbanService
     */
    private IbanService $service;

    /**
     * Initialise le contrôleur avec le service IBAN
     */
    public function __construct()
    {
        $this->service = new IbanService(new HttpClient());
    }

    /**
     * Point d'entrée API pour la validation d'IBAN
     * 
     * Traite une requête POST contenant un IBAN à valider
     * et renvoie les informations bancaires associées.
     * 
     * @return void
     */
    public function validate(): void
    {
        // Vérification de la méthode HTTP
        if (!$this->isPost()) {
            $this->sendError('Méthode non autorisée', 405);
            return;
        }

        // Récupération et nettoyage des données
        $data = sanitizeInput($this->getJsonInput());
        $iban = strtoupper($data['iban'] ?? '');

        // Validation du format IBAN
        if (!$iban || !$this->isValidIbanFormat($iban)) {
            $this->sendError('IBAN invalide ou manquant', 400, [
                'iban' => 'Format IBAN invalide (ex: FR76...)'
            ]);
            return;
        }

        try {
            // Appel du service de validation
            $result = $this->service->validate($iban);
            $this->sendSuccess($result);
        } catch (\Throwable $e) {
            // Gestion des erreurs et logging
            logError('IBAN validation failed', ['message' => $e->getMessage()]);
            $this->sendError('Le service est temporairement indisponible. Veuillez réessayer plus tard.', 503);
        }
    }

    /**
     * Vérifie si le format de l'IBAN est valide syntaxiquement
     * 
     * Vérifie que l'IBAN commence par un code pays (2 lettres)
     * suivi d'un code de contrôle (2 chiffres) et d'un numéro de compte
     * (jusqu'à 30 caractères alphanumériques)
     * 
     * @param string $iban IBAN à valider
     * @return bool true si le format est valide, false sinon
     */
    private function isValidIbanFormat(string $iban): bool
    {
        return preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', str_replace(' ', '', $iban));
    }
}
