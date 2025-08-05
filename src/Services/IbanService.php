<?php

namespace App\Services;

use App\Utils\HttpClient;

class IbanService
{
    private HttpClient $httpClient;
    private string $baseUrl;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->baseUrl = 'https://openiban.com/validate';
    }

    public function validate(string $iban): array
    {
        $url = "{$this->baseUrl}/" . urlencode($iban) . "?getBIC=true";

        $response = $this->httpClient->get($url, 10);

        if (!$response || $response['status_code'] !== 200) {
            throw new \RuntimeException('API OpenIBAN indisponible');
        }

        $data = json_decode($response['body'], true);

        if (!is_array($data) || !isset($data['valid'])) {
            throw new \RuntimeException('Réponse invalide de l\'API IBAN');
        }

        return [
            'iban'    => $this->formatIban($iban),
            'valid'   => $data['valid'],
            'bank'    => $data['bankData']['name'] ?? null,
            'bic'     => $data['bankData']['bic'] ?? null,
            'country' => $this->mapCountryCodeToName($data['countryCode'] ?? null),
        ];
    }

    private function formatIban(string $iban): string
    {
        return trim(chunk_split(preg_replace('/\s+/', '', strtoupper($iban)), 4, ' '));
    }

    private function mapCountryCodeToName(?string $code): ?string
    {
        $map = [
            'FR' => 'France',
            'DE' => 'Germany',
            'ES' => 'Spain',
            'BE' => 'Belgium',
            'IT' => 'Italy',
        ];
        return $map[$code] ?? null;
    }
}
