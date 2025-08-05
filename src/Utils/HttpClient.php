<?php

namespace App\Utils;

class HttpClient
{
    private array $defaultHeaders;
    private int $maxRedirects = 3;

    public function __construct(array $defaultHeaders = [])
    {
        $this->defaultHeaders = array_merge([
            'User-Agent'      => 'CM Services/1.0',
            'Accept'          => 'application/json',
            'Accept-Encoding' => 'gzip'
        ], $defaultHeaders);
    }

    public function get(string $url, int $timeout = 10): array
    {
        if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with($url, 'https://')) {
            return [
                'status_code' => 0,
                'body' => null,
                'error' => 'Invalid or non-HTTPS URL'
            ];
        }

        $headers = [];
        foreach ($this->defaultHeaders as $name => $value) {
            $headers[] = "{$name}: {$value}";
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => $this->maxRedirects,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => '',
            CURLOPT_HEADER         => false,
        ]);

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        return [
            'status_code' => $statusCode ?: 0,
            'body'        => $body ?: null,
            'error'       => $body === false ? $error : null
        ];
    }
}
