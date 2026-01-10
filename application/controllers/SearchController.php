<?php

declare(strict_types=1);

namespace Icinga\Module\Map\Controllers;

use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Map\Web\Controller\MapController;
use ipl\Stdlib\Filter as IplFilter;
use OpenCage\Geocoder\Geocoder;

final class SearchController extends MapController
{
    private ?Geocoder $geocoder = null;
    private int $limit = 5;

    public function init(): void
    {
        parent::init();
        $this->initializeGeocoder();
    }

    private function initializeGeocoder(): void
    {
        $apiKey = $this->Config()->get('map', 'opencage_apikey', '');
        
        if ($apiKey !== '') {
            $this->geocoder = new Geocoder($apiKey);
        }
    }

    public function indexAction(): never
    {
        $query = strtolower($this->params->shift('q', ''));
        $callback = $this->params->shift('jsonp', '');
        $this->limit = (int) $this->params->shift('limit', 5);
        $lite = (bool) $this->params->shift('lite', 0);

        $results = [
            'ocg'      => $this->opencageSearch($query),
            'hosts'    => $lite ? [] : $this->searchObjects($query, 'host'),
            'services' => $lite ? [] : $this->searchObjects($query, 'service'),
        ];

        $this->outputJsonp($callback, $results);
    }

    private function opencageSearch(string $query): array
    {
        if ($this->geocoder === null || $query === '') {
            return [];
        }

        $result = $this->geocoder->geocode($query, ['limit' => $this->limit]);

        if (!$result || ($result['total_results'] ?? 0) === 0) {
            return [];
        }

        return array_map(
            static fn(array $el): array => [
                'name'   => $el['formatted'],
                'center' => [
                    'lat' => $el['geometry']['lat'],
                    'lng' => $el['geometry']['lng']
                ],
                'icon'   => 'globe'
            ],
            $result['results']
        );
    }

    private function searchObjects(string $query, string $objectType): array
    {
        if ($query === '') {
            return [];
        }

        $searchString = "*{$query}*";
        $db = $this->icingadbUtils->getDb();

        $dbQuery = match ($objectType) {
            'service' => Service::on($db)->with('host'),
            default   => Host::on($db),
        };

        $dbQuery
            ->filter(IplFilter::like("{$objectType}.vars.geolocation", '*'))
            ->filter(IplFilter::any(
                IplFilter::like("{$objectType}.name", $searchString),
                IplFilter::like("{$objectType}.display_name", $searchString)
            ))
            ->limit($this->limit);

        $this->icingadbUtils->applyRestrictions($dbQuery);

        return $this->processSearchResults($dbQuery->execute(), $objectType);
    }

    private function processSearchResults(iterable $result, string $objectType): array
    {
        $results = [];

        foreach ($result as $object) {
            $coordinates = $object->vars['geolocation'] ?? null;
            
            if ($coordinates === null || !preg_match($this->coordinatePattern, $coordinates)) {
                continue;
            }

            [$lat, $lng] = explode(',', $coordinates);

            $results[] = match ($objectType) {
                'service' => [
                    'id'     => "{$object->host->name}!{$object->name}",
                    'name'   => "{$object->name} ({$object->host->name})",
                    'center' => ['lat' => $lat, 'lng' => $lng],
                    'icon'   => 'service'
                ],
                default => [
                    'id'     => $object->name,
                    'name'   => $object->display_name,
                    'center' => ['lat' => $lat, 'lng' => $lng],
                    'icon'   => 'host'
                ],
            };
        }

        return $results;
    }

    private function outputJsonp(string $callback, array $data): never
    {
        header('Content-Type: application/javascript; charset=utf-8');
        echo $callback . '(' . json_encode($data, JSON_THROW_ON_ERROR) . ');';
        exit();
    }
}
