<?php

declare(strict_types=1);

namespace OpenCage\Geocoder;

use RuntimeException;

abstract class AbstractGeocoder
{
    private const DEFAULT_TIMEOUT = 10;
    protected const API_URL = 'https://api.opencagedata.com/geocode/v1/json?';

    protected ?string $key = null;
    protected int $timeout = self::DEFAULT_TIMEOUT;

    public function __construct(?string $key = null)
    {
        if ($key !== null && $key !== '') {
            $this->key = $key;
        }
    }

    public function setKey(string $key): self
    {
        $this->key = $key;
        return $this;
    }

    public function setTimeout(int $timeout): self
    {
        $this->timeout = $timeout;
        return $this;
    }

    protected function fetchJson(string $url): ?string
    {
        if (function_exists('curl_version')) {
            return $this->fetchWithCurl($url);
        }

        if (ini_get('allow_url_fopen')) {
            return $this->fetchWithFopen($url);
        }

        throw new RuntimeException(
            'PHP is not compiled with CURL support and allow_url_fopen is disabled'
        );
    }

    private function fetchWithFopen(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => $this->timeout,
                'ignore_errors' => true
            ]
        ]);

        $result = @file_get_contents($url, false, $context);
        
        return $result !== false ? $result : null;
    }

    private function fetchWithCurl(string $url): ?string
    {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($result === false) {
            return null;
        }

        return $result;
    }

    abstract public function geocode(string $query, array $params = []): ?array;
}
