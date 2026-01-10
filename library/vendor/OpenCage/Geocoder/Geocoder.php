<?php

declare(strict_types=1);

namespace OpenCage\Geocoder;

use InvalidArgumentException;

final class Geocoder extends AbstractGeocoder
{
    public function geocode(string $query, array $params = []): ?array
    {
        if ($this->key === null || $this->key === '') {
            throw new InvalidArgumentException('Missing API key');
        }

        if ($query === '') {
            return null;
        }

        $url = $this->buildUrl($query, $params);
        $response = $this->fetchJson($url);

        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        
        return is_array($decoded) ? $decoded : null;
    }

    private function buildUrl(string $query, array $params): string
    {
        $queryParams = array_merge(
            ['q' => $query],
            $params,
            ['key' => $this->key]
        );

        return self::API_URL . http_build_query($queryParams);
    }
}
