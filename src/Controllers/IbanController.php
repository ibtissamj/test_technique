<?php

namespace App\Controllers;

use App\Services\IbanService;
use App\Utils\HttpClient;

class IbanController extends BaseController
{
    private IbanService $service;

    public function __construct()
    {
        $this->service = new IbanService(new HttpClient());
    }

    public function validate(): void
    {
        if (!$this->isPost()) {
            $this->sendError('Méthode non autorisée', 405);
            return;
        }

        $data = sanitizeInput($this->getJsonInput());
        $iban = strtoupper($data['iban'] ?? '');

        if (!$iban || !$this->isValidIbanFormat($iban)) {
            $this->sendError('IBAN invalide ou manquant', 400, [
                'iban' => 'Format IBAN invalide (ex: FR76...)'
            ]);
            return;
        }

        try {
            $result = $this->service->validate($iban);
            $this->sendSuccess($result);
        } catch (\Throwable $e) {
            logError('IBAN validation failed', ['message' => $e->getMessage()]);
            $this->sendError('Le service est temporairement indisponible. Veuillez réessayer plus tard.', 503);
        }
    }

    private function isValidIbanFormat(string $iban): bool
    {
        return preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{1,30}$/', str_replace(' ', '', $iban));
    }
}
