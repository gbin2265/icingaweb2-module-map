<?php

declare(strict_types=1);

namespace Icinga\Module\Map\Controllers;

use Icinga\Data\Filter\FilterException;
use Icinga\Util\Json;
use ipl\Html\Attributes;
use ipl\Html\HtmlElement;
use ipl\Web\Compat\CompatController;

final class IndexController extends CompatController
{
    private const PARAMETER_DEFAULTS = [
        'default_zoom'            => '4',
        'default_long'            => '13.377485',
        'default_lat'             => '52.515855',
        'min_zoom'                => '2',
        'max_zoom'                => '19',
        'max_native_zoom'         => '19',
        'disable_cluster_at_zoom' => null,
        'cluster_problem_count'   => 0,
        'popup_mouseover'         => 0,
        'tile_url'                => '//{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        'opencage_apikey'         => '',
    ];

    private const FILTER_PATTERN = '/^([_]{0,1}(host|service))|\(|(object|state)Type/';

    private const SERVICE_STATES = [
        0  => ['OK', 'OK'],
        1  => ['WARNING', 'WARNING'],
        2  => ['CRITICAL', 'CRITICAL'],
        3  => ['UNKNOWN', 'UNKNOWN'],
        99 => ['PENDING', 'PENDING'],
    ];

    public function indexAction(): void
    {
        $config = $this->Config();
        $mapConfig = null;
        $mapName = null;

        // Load stored map if requested
        if ($this->params->has('load')) {
            [$mapName, $mapConfig] = $this->loadStoredMap();
        }

        // Merge user preferences
        $this->mergeUserPreferences($config);

        // Resolve all parameters
        $viewParams = $this->resolveParameters($config, $mapConfig, $mapName);

        // Build URL parameters
        $urlParameters = $this->buildUrlParameters($mapConfig, $mapName);

        // Create map elements
        $this->createMapElements($viewParams, $urlParameters);
    }

    private function loadStoredMap(): array
    {
        $mapName = $this->params->get('load');

        if (!preg_match('/^[\w]+$/', $mapName)) {
            throw new FilterException('Invalid character in map name. Allowed characters: a-zA-Z0-9_');
        }

        $mapConfig = $this->Config('maps');
        
        if (!$mapConfig->hasSection($mapName)) {
            throw new FilterException("Could not find stored map with name = {$mapName}");
        }

        return [$mapName, $mapConfig];
    }

    private function mergeUserPreferences(object $config): void
    {
        $userPreferences = $this->Auth()->getUser()->getPreferences();
        
        if ($userPreferences->has('map')) {
            $config->getSection('map')->merge($userPreferences->get('map'));
        }
    }

    private function resolveParameters(object $config, ?object $mapConfig, ?string $mapName): array
    {
        $params = [];

        foreach (self::PARAMETER_DEFAULTS as $parameter => $default) {
            if ($this->params->has($parameter)) {
                $params[$parameter] = $this->params->get($parameter);
            } elseif ($mapName !== null && $mapConfig->getSection($mapName)->offsetExists($parameter)) {
                $params[$parameter] = $mapConfig->get($mapName, $parameter);
            } else {
                $params[$parameter] = $config->get('map', $parameter, $default);
            }

            // Also set on view for backwards compatibility
            $this->view->$parameter = $params[$parameter];
        }

        // Calculate disable_cluster_at_zoom if not set
        if (!$params['disable_cluster_at_zoom']) {
            $params['disable_cluster_at_zoom'] = (int) $params['max_zoom'] - 1;
            $this->view->disable_cluster_at_zoom = $params['disable_cluster_at_zoom'];
        }

        return $params;
    }

    private function buildUrlParameters(?object $mapConfig, ?string $mapName): array
    {
        $urlParameters = $this->filterArray($this->params->toArray(false), self::FILTER_PATTERN);

        if ($mapName !== null) {
            $mapParameters = $this->filterArray($mapConfig->getSection($mapName)->toArray(), self::FILTER_PATTERN);
            $urlParameters = array_merge($mapParameters, $urlParameters);
        }

        array_walk($urlParameters, static function (&$value, string $key): void {
            $value = "{$key}={$value}";
        });

        return $urlParameters;
    }

    private function createMapElements(array $params, array $urlParameters): void
    {
        $config = $this->Config();
        $id = uniqid();
        $showHost = $this->params->get('showHost');

        $this->view->dashletHeight = $config->get('map', 'dashlet_height', '300');

        $mapAttrs = [
            'map_default_zoom'        => $params['default_zoom'] !== '' ? (int) $params['default_zoom'] : 'null',
            'map_default_long'        => $this->sanitizeCoordinate($params['default_long']),
            'map_default_lat'         => $this->sanitizeCoordinate($params['default_lat']),
            'map_max_zoom'            => (int) $params['max_zoom'],
            'map_max_native_zoom'     => (int) $params['max_native_zoom'],
            'map_min_zoom'            => (int) $params['min_zoom'],
            'disable_cluster_at_zoom' => (int) $params['disable_cluster_at_zoom'],
            'tile_url'                => preg_replace("/[';]/", '', $params['tile_url']),
            'cluster_problem_count'   => (int) $params['cluster_problem_count'],
            'popup_mouseover'         => (int) $params['popup_mouseover'],
            'map_show_host'           => $showHost !== null ? preg_replace("/[';]/", '', $showHost) : 'null',
            'url_parameters'          => implode('&', $urlParameters),
            'id'                      => $id,
            'dashlet'                 => $this->view->compact ? 'true' : 'false',
            'expand'                  => $this->params->get('expand') ? 'true' : 'false',
            'isUsingIcingadb'         => 'true',
            'service_status'          => $this->getTranslatedServiceStates(),
            'translation'             => $this->getTranslations(),
        ];

        $this->addContent(new HtmlElement('div', Attributes::create([
            'id'             => 'map-script',
            'data-map-attrs' => Json::encode($mapAttrs)
        ])));

        $this->addContent(new HtmlElement('div', Attributes::create([
            'id'    => "map-{$id}",
            'class' => 'map' . ($this->view->compact ? ' compact' : '')
        ])));
    }

    private function sanitizeCoordinate(string $value): string
    {
        return $value !== '' ? preg_replace('/[^0-9.,\-]/', '', $value) : 'null';
    }

    private function getTranslatedServiceStates(): array
    {
        $translated = [];
        
        foreach (self::SERVICE_STATES as $code => [$label, $class]) {
            $translated[$code] = [$this->translate($label, 'icinga.state'), $class];
        }

        return $translated;
    }

    private function getTranslations(): array
    {
        return [
            'btn-zoom-in'    => $this->translate('Zoom in'),
            'btn-zoom-out'   => $this->translate('Zoom out'),
            'btn-dashboard'  => $this->translate('Add to dashboard'),
            'btn-fullscreen' => $this->translate('Fullscreen'),
            'btn-default'    => $this->translate('Show default view'),
            'btn-locate'     => $this->translate('Show current location'),
            'host-down'      => $this->translate('Host is down'),
        ];
    }

    private function filterArray(array $array, string $pattern): array
    {
        $matches = preg_grep($pattern, array_keys($array));
        return array_intersect_key($array, array_flip($matches));
    }
}
