<?php

namespace App\Services;

use App\Utils\HttpClient;

/**
 * Service de conversion de devises avec cache et gestion d'erreurs robuste
 * 
 * @author Ibtissam Jennate
 * 
 */
class CurrencyService
{
    private HttpClient $httpClient;
    private string $baseUrl;
    private ?string $apiKey;
    private array $cacheConfig;

    /**
     * @param HttpClient $httpClient Client HTTP pour les appels API
     */
    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
        $this->baseUrl = rtrim(API_CONFIG['exchange_rates']['base_url'], '/');
        $this->apiKey = API_CONFIG['exchange_rates']['api_key'] ?? null;
        $this->cacheConfig = CACHE_CONFIG;
    }

    /**
     * Convertit un montant d'une devise à une autre
     *
     * @param float $amount Montant à convertir
     * @param string $from Devise source (code ISO 3 lettres)
     * @param string $to Devise cible (code ISO 3 lettres)
     * @return array Résultat de la conversion avec montant et taux
     * @throws \InvalidArgumentException Si les paramètres sont invalides
     * @throws \RuntimeException Si l'API est indisponible
     */
    public function convert(float $amount, string $from, string $to): array
    {
        $this->validateCurrencyCode($from);
        $this->validateCurrencyCode($to);
        $this->validateAmount($amount);

        if ($from === $to) {
            return [
                'converted_amount' => number_format($amount, 2, '.', ''),
                'exchange_rate' => '1.0000',
                'from_currency' => $from,
                'to_currency' => $to,
                'timestamp' => time()
            ];
        }

        $rate = $this->getRate($from, $to);
        $converted = round($amount * $rate, 2);

        return [
            'converted_amount' => number_format($converted, 2, '.', ''),
            'exchange_rate' => number_format($rate, 4, '.', ''),
            'from_currency' => $from,
            'to_currency' => $to,
            'original_amount' => number_format($amount, 2, '.', ''),
            'timestamp' => time()
        ];
    }

    /**
     * Récupère les taux de change pour plusieurs devises
     *
     * @param string $base Devise de base
     * @param array $symbols Liste des devises cibles
     * @return array Taux de change
     * @throws \RuntimeException Si l'API est indisponible
     */
    public function getRates(string $base, array $symbols): array
    {
        $this->validateCurrencyCode($base);
        
        foreach ($symbols as $symbol) {
            $this->validateCurrencyCode($symbol);
        }

        return $this->fetchRates($base, $symbols);
    }

    /**
     * Récupère le taux de change entre deux devises
     *
     * @param string $base Devise de base
     * @param string $symbol Devise cible
     * @return float Taux de change
     * @throws \RuntimeException Si le taux est introuvable
     */
    private function getRate(string $base, string $symbol): float
    {
        $rates = $this->fetchRates($base, [$symbol]);
        
        if (!isset($rates[$symbol])) {
            throw new \RuntimeException("Taux de change introuvable pour {$base}/{$symbol}");
        }
        
        return (float)$rates[$symbol];
    }

    /**
     * Appelle l'API externe avec gestion du cache et retry
     *
     * @param string $base Devise de base
     * @param array $symbols Liste des devises cibles
     * @return array Taux de change
     * @throws \RuntimeException Si l'API est indisponible après plusieurs tentatives
     */
    private function fetchRates(string $base, array $symbols): array
    {
        $symbolsStr = implode(',', $symbols);
        $cacheKey = 'rates_' . $base . '_' . md5($symbolsStr);
        
        // Tentative de lecture du cache
        $cachedData = $this->getCachedRates($cacheKey);
        if ($cachedData !== null) {
            return $cachedData;
        }

        // Appel API avec retry
        $maxRetries = 3;
        $retryDelay = 1; // seconde

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $rates = $this->callExchangeRatesApi($base, $symbolsStr);
                
                // Sauvegarde en cache
                $this->cacheRates($cacheKey, $rates);
                
                return $rates;
                
            } catch (\RuntimeException $e) {
                if ($attempt === $maxRetries) {
                    logError('Currency API failed after all retries', [
                        'base' => $base,
                        'symbols' => $symbolsStr,
                        'error' => $e->getMessage()
                    ]);
                    throw $e;
                }
                
                // Attendre avant de réessayer
                sleep($retryDelay * $attempt);
            }
        }

        throw new \RuntimeException('Service de taux de change indisponible');
    }

    /**
     * Effectue l'appel à l'API externe
     *
     * @param string $base Devise de base
     * @param string $symbolsStr Devises cibles (séparées par virgule)
     * @return array Taux de change
     * @throws \RuntimeException Si l'appel API échoue
     */
    private function callExchangeRatesApi(string $base, string $symbolsStr): array
    {
        $endpoint = API_CONFIG['exchange_rates']['endpoints']['latest'];
        $params = [
            'base' => $base,
            'symbols' => $symbolsStr
        ];
        
        if (!empty($this->apiKey)) {
            $params['access_key'] = $this->apiKey;
        }
        
        $url = $this->baseUrl . '/' . $endpoint . '?' . http_build_query($params);

        $response = $this->httpClient->get($url, 10);
        
        if ($response === false) {
            throw new \RuntimeException('Échec de connexion à l\'API de taux de change');
        }
        
        if ($response['status_code'] !== 200) {
            throw new \RuntimeException("Erreur API HTTP {$response['status_code']}");
        }

        $data = json_decode($response['body'], true);
        
        if (!is_array($data)) {
            throw new \RuntimeException('Réponse API invalide : JSON malformé');
        }
        
        if (!($data['success'] ?? false)) {
            $errorMessage = $data['error']['info'] ?? 'Erreur API inconnue';
            throw new \RuntimeException("Erreur API : {$errorMessage}");
        }

        $rates = $data['rates'] ?? [];
        
        if (empty($rates)) {
            throw new \RuntimeException('Aucun taux de change retourné par l\'API');
        }

        return $rates;
    }

    /**
     * Récupère les taux depuis le cache si valide
     *
     * @param string $cacheKey Clé de cache
     * @return array|null Données cachées ou null si invalide/inexistant
     */
    private function getCachedRates(string $cacheKey): ?array
    {
        if (!$this->cacheConfig['enabled']) {
            return null;
        }

        $cacheFile = $this->cacheConfig['path'] . $cacheKey . '.json';
        
        if (!file_exists($cacheFile)) {
            return null;
        }

        $mtime = filemtime($cacheFile);
        if ($mtime === false || (time() - $mtime) >= $this->cacheConfig['ttl']) {
            // Cache expiré, on le supprime
            @unlink($cacheFile);
            return null;
        }

        $cached = @file_get_contents($cacheFile);
        if ($cached === false) {
            return null;
        }

        $data = json_decode($cached, true);
        return is_array($data) ? $data : null;
    }

    /**
     * Sauvegarde les taux en cache
     *
     * @param string $cacheKey Clé de cache
     * @param array $rates Taux à sauvegarder
     */
    private function cacheRates(string $cacheKey, array $rates): void
    {
        if (!$this->cacheConfig['enabled']) {
            return;
        }

        $cacheDir = $this->cacheConfig['path'];
        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir, 0755, true)) {
                logError('Impossible de créer le répertoire de cache', ['path' => $cacheDir]);
                return;
            }
        }

        $cacheFile = $cacheDir . $cacheKey . '.json';
        $jsonData = json_encode($rates, JSON_UNESCAPED_UNICODE);
        
        if (@file_put_contents($cacheFile, $jsonData, LOCK_EX) === false) {
            logError('Impossible d\'écrire dans le cache', ['file' => $cacheFile]);
        }
    }

    /**
     * Valide un code de devise
     *
     * @param string $currencyCode Code devise à valider
     * @throws \InvalidArgumentException Si le code est invalide
     */
    private function validateCurrencyCode(string $currencyCode): void
    {
        if (!isset(SUPPORTED_CURRENCIES[$currencyCode])) {
            throw new \InvalidArgumentException("Devise non supportée: {$currencyCode}");
        }
    }

    /**
     * Valide un montant
     *
     * @param float $amount Montant à valider
     * @throws \InvalidArgumentException Si le montant est invalide
     */
    private function validateAmount(float $amount): void
    {
        if ($amount < LIMITS['min_conversion_amount'] || $amount > LIMITS['max_conversion_amount']) {
            throw new \InvalidArgumentException(
                "Montant invalide. Doit être entre " . 
                LIMITS['min_conversion_amount'] . " et " . 
                LIMITS['max_conversion_amount']
            );
        }
    }

    /**
     * Nettoie le cache expiré
     *
     * @return int Nombre de fichiers supprimés
     */
    public function cleanExpiredCache(): int
    {
        if (!$this->cacheConfig['enabled']) {
            return 0;
        }

        $cacheDir = $this->cacheConfig['path'];
        if (!is_dir($cacheDir)) {
            return 0;
        }

        $deleted = 0;
        $files = glob($cacheDir . 'rates_*.json');
        
        foreach ($files as $file) {
            $mtime = filemtime($file);
            if ($mtime !== false && (time() - $mtime) >= $this->cacheConfig['ttl']) {
                if (@unlink($file)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}