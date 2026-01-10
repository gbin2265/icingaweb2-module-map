<?php

declare(strict_types=1);

namespace Icinga\Module\Map\Forms\Config;

use Icinga\Forms\ConfigForm;

final class GeneralConfigForm extends ConfigForm
{
    private const PLACEHOLDERS = [
        'lat'      => '52.520645',
        'long'     => '13.409779',
        'zoom'     => '6',
        'max_zoom' => '19',
        'min_zoom' => '2',
        'tile_url' => '//\{s\}.tile.openstreetmap.org/\{z\}/\{x\}/\{y\}.png',
        'height'   => '300',
    ];

    public function init(): void
    {
        $this->setName('form_config_map_general');
        $this->setSubmitLabel($this->translate('Save Changes'));
    }

    public function createElements(array $formData): void
    {
        $this->addCoordinateElements();
        $this->addZoomElements();
        $this->addDisplayElements();
    }

    private function addCoordinateElements(): void
    {
        $this->addElement('text', 'map_default_lat', [
            'placeholder' => self::PLACEHOLDERS['lat'],
            'label'       => $this->translate('Default latitude (WGS84)'),
            'description' => $this->translate('Default map position (latitude)'),
            'required'    => false
        ]);

        $this->addElement('text', 'map_default_long', [
            'placeholder' => self::PLACEHOLDERS['long'],
            'label'       => $this->translate('Default longitude (WGS84)'),
            'description' => $this->translate('Default map position (longitude)'),
            'required'    => false
        ]);
    }

    private function addZoomElements(): void
    {
        $this->addElement('text', 'map_default_zoom', [
            'placeholder' => self::PLACEHOLDERS['zoom'],
            'label'       => $this->translate('Default zoom level'),
            'description' => $this->translate('Default zoom level of the map'),
            'required'    => false
        ]);

        $this->addElement('text', 'map_max_zoom', [
            'placeholder' => self::PLACEHOLDERS['max_zoom'],
            'label'       => $this->translate('Maximum zoom level'),
            'description' => $this->translate('Maximum zoom level of the map'),
            'required'    => false
        ]);

        $this->addElement('text', 'map_max_native_zoom', [
            'placeholder' => self::PLACEHOLDERS['max_zoom'],
            'label'       => $this->translate('Maximum native zoom level'),
            'description' => $this->translate('Maximum zoom level natively supported by the map'),
            'required'    => false
        ]);

        $this->addElement('text', 'map_min_zoom', [
            'placeholder' => self::PLACEHOLDERS['min_zoom'],
            'label'       => $this->translate('Minimal zoom level'),
            'description' => $this->translate('Minimal zoom level of the map'),
            'required'    => false
        ]);

        $this->addElement('text', 'map_disable_cluster_at_zoom', [
            'label'       => $this->translate('Disable clustering at zoomlevel'),
            'description' => $this->translate('Don\'t cluster marker at a certain zoomlevel. Use 1 for disabling clustering'),
            'required'    => false
        ]);
    }

    private function addDisplayElements(): void
    {
        $this->addElement('text', 'map_tile_url', [
            'placeholder' => self::PLACEHOLDERS['tile_url'],
            'label'       => $this->translate('URL for tile server'),
            'description' => $this->translate('Escaped server url, for leaflet tilelayer'),
            'required'    => false
        ]);

        $this->addElement('text', 'map_opencage_apikey', [
            'placeholder' => 'OpenCage Geocoder API KEY',
            'label'       => $this->translate('OpenCage API key'),
            'description' => $this->translate('Your personal OpenCage Geocoder API key'),
            'required'    => false
        ]);

        $this->addElement('text', 'map_dashlet_height', [
            'placeholder' => self::PLACEHOLDERS['height'],
            'label'       => $this->translate('Dashlet height'),
            'description' => $this->translate('Dashlet height'),
            'required'    => false
        ]);

        $this->addElement('select', 'map_stateType', [
            'label'        => $this->translate('State type'),
            'description'  => $this->translate('State type for status indication'),
            'multiOptions' => [
                'soft' => 'soft',
                'hard' => 'hard'
            ]
        ]);

        $this->addElement('checkbox', 'map_cluster_problem_count', [
            'label'       => $this->translate('Show number of problems in cluster'),
            'description' => $this->translate('Show number of problems in cluster instead of the number of markers'),
            'required'    => false,
            'default'     => false
        ]);

        $this->addElement('checkbox', 'map_popup_mouseover', [
            'label'       => $this->translate('Show popup on mouseover'),
            'description' => $this->translate('Show popup when hovering the object'),
            'required'    => false,
            'default'     => false
        ]);
    }
}
