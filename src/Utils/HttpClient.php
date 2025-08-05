<?php

namespace App\Utils;

/**
 * Client HTTP pour les appels d'API externes
 * 
 * Cette classe encapsule les fonctionnalités de communication HTTP
 * avec les services externes en utilisant cURL. Elle gère la construction
 * des en-têtes, les timeouts, les redirections et le traitement des réponses.
 * 
 * @author Ibtissam Jennate
 */
class HttpClient
{
    /**
     * En-têtes par défaut pour toutes les requêtes
     * 
     * @var array
     */
    private array $defaultHeaders;
    
    /**
     * Nombre maximal de redirections autorisées
     * 
     * @var int
     */
    private int $maxRedirects = 3;

    /**
     * Initialise un client HTTP avec des en-têtes par défaut
     * 
     * @param array $defaultHeaders En-têtes HTTP personnalisés à fusionner avec les valeurs par défaut
     */
    public function __construct(array $defaultHeaders = [])
    {
        $this->defaultHeaders = array_merge([
            'User-Agent'      => 'CM Services/1.0',
            'Accept'          => 'application/json',
            'Accept-Encoding' => 'gzip'
        ], $defaultHeaders);
    }

    /**
     * Exécute une requête HTTP GET vers l'URL spécifiée
     * 
     * Vérifie la validité de l'URL, applique les en-têtes définis, et
     * exécute la requête avec cURL. Gère les timeouts et les redirections.
     * 
     * @param string $url     URL à appeler (doit être HTTPS)
     * @param int    $timeout Délai maximum d'attente en secondes
     * 
     * @return array Tableau contenant le code de statut, le corps de la réponse et les erreurs éventuelles
     */
    public function get(string $url, int $timeout = 10): array
    {
        // Validation de l'URL (sécurité: accepte uniquement les URLs HTTPS)
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
            return [
                'status_code' => 0,
                'body' => null,
                'error' => 'Invalid or non-HTTPS URL'
            ];
        }

        // Préparation des en-têtes pour cURL
        $headers = [];
        foreach ($this->defaultHeaders as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        $ch = curl_init();

        // Configuration de la session cURL avec les options sécurisées
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,  // Renvoie la réponse au lieu de l'afficher
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => true,  // Suit les redirections
            CURLOPT_MAXREDIRS      => $this->maxRedirects,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => '',    // Accepte l'encodage gzip
            CURLOPT_HEADER         => false, // Ne renvoie pas les en-têtes
        ]);

        // Exécution de la requête
        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        // Création du tableau de réponse normalisé
        return [
            'status_code' => $statusCode ?: 0,
            'body'        => $body ?: null,
            'error'       => $body === false ? $error : null
        ];
    }
}
