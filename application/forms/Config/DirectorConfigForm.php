<?php

declare(strict_types=1);

namespace Icinga\Module\Map\Forms\Config;

use Icinga\Forms\ConfigForm;

final class DirectorConfigForm extends ConfigForm
{
    private const PLACEHOLDER = '(use map modules configuration)';

    private const FIELDS = [
        'director_default_lat'      => ['Default latitude (WGS84)', 'Default map position (latitude)'],
        'director_default_long'     => ['Default longitude (WGS84)', 'Default map position (longitude)'],
        'director_default_zoom'     => ['Default zoom level', 'Default zoom level of the map'],
        'director_max_zoom'         => ['Maximum zoom level', 'Maximum zoom level of the map'],
        'director_max_native_zoom'  => ['Maximum native zoom level', 'Maximum zoom level natively supported by the map'],
        'director_min_zoom'         => ['Minimal zoom level', 'Minimal zoom level of the map'],
        'director_tile_url'         => ['URL for tile server', 'Escaped server url, for leaflet tilelayer'],
    ];

    public function init(): void
    {
        $this->setName('form_config_director');
        $this->setSubmitLabel($this->translate('Save Changes'));
    }

    public function createElements(array $formData): void
    {
        foreach (self::FIELDS as $name => [$label, $description]) {
            $this->addElement('text', $name, [
                'placeholder' => self::PLACEHOLDER,
                'label'       => $this->translate($label),
                'description' => $this->translate($description),
                'required'    => false
            ]);
        }
    }
}
