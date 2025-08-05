<?php

namespace App\Services;

use App\Utils\HttpClient;

/**
 * Service pour la validation des IBAN via l'API OpenIBAN.
 */
class IbanService
{
    private HttpClient $httpClient;
    private string $baseUrl;

    /**
     * Constructeur du service IBAN.
     *
     * @param HttpClient $httpClient Client HTTP pour les requêtes externes.
     */
    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->baseUrl = 'https://openiban.com/validate';
    }

    /**
     * Valide un IBAN en utilisant l'API OpenIBAN.
     *
     * @param string $iban L'IBAN à valider.
     * @return array Informations sur l'IBAN.
     * @throws \RuntimeException En cas d'erreur avec l'API ou la réponse.
     */
    public function validate(string $iban): array
    {
        $url = "{$this->baseUrl}/" . urlencode($iban) . "?getBIC=true";

        try {
            $response = $this->httpClient->get($url, 10);
        } catch (\Throwable $e) {
            // Gestion des erreurs de connexion ou d'exécution
            throw new \RuntimeException('Erreur lors de la connexion à l\'API OpenIBAN : ' . $e->getMessage());
        }

        if (!$response || !isset($response['status_code']) || $response['status_code'] !== 200) {
            throw new \RuntimeException('API OpenIBAN indisponible ou réponse invalide');
        }

        $data = json_decode($response['body'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Erreur lors du décodage de la réponse JSON de l\'API IBAN');
        }

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

    /**
     * Formate l'IBAN en groupes de 4 caractères séparés par des espaces.
     *
     * @param string $iban L'IBAN à formater.
     * @return string IBAN formaté.
     */
    private function formatIban(string $iban): string
    {
        return trim(chunk_split(preg_replace('/\s+/', '', strtoupper($iban)), 4, ' '));
    }

    /**
     * Convertit le code pays en nom de pays.
     *
     * @param string|null $code Code pays (ex: FR, DE).
     * @return string|null Nom du pays ou null si inconnu.
     */
    private function mapCountryCodeToName(?string $code): ?string
    {
        $map = [
            'FR' => 'France',
            'DE' => 'Allemagne',
            'ES' => 'Espagne',
            'BE' => 'Belgique',
            'IT' => 'Italie',
        ];
        return $map[$code] ?? null;
    }
}

