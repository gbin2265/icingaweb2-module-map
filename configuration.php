<?php

declare(strict_types=1);

// Menu section
$section = $this->menuSection(N_('Maps'), ['icon' => 'globe']);

$section->add(N_($this->translate('Default map')), [
    'icon'        => 'globe',
    'description' => $this->translate('Visualize your hosts and services on a map'),
    'url'         => 'map',
    'priority'    => 10
]);

// Stylesheets
$this->provideCssFile('vendor/leaflet.css');
$this->provideCssFile('vendor/MarkerCluster.css');
$this->provideCssFile('vendor/MarkerCluster.Default.css');
$this->provideCssFile('vendor/L.Control.Locate.css');
$this->provideCssFile('vendor/easy-button.css');
$this->provideCssFile('vendor/leaflet.awesome-markers.css');
$this->provideCssFile('vendor/leaflet.modal.css');
$this->provideCssFile('vendor/L.Control.OpenCageData.Search.min.css');
$this->provideCssFile('vendor/spin.css');

// JavaScript libraries
$this->provideJsFile('vendor/spin.js');
$this->provideJsFile('vendor/leaflet.js');
$this->provideJsFile('vendor/leaflet.spin.js');
$this->provideJsFile('vendor/leaflet.markercluster.js');
$this->provideJsFile('vendor/L.Control.Locate.js');
$this->provideJsFile('vendor/easy-button.js');
$this->provideJsFile('vendor/leaflet.awesome-markers.js');
$this->provideJsFile('vendor/Leaflet.Modal.js');
$this->provideJsFile('vendor/L.Control.OpenCageSearch.js');

// Configuration tabs
$this->provideConfigTab('config', [
    'title' => $this->translate('Configure the map module'),
    'label' => $this->translate('Configuration'),
    'url'   => 'config'
]);

// Director integration (optional)
$moduleManager = $this->app->getModuleManager();
if ($moduleManager->hasEnabled('mapDatatype') && $moduleManager->hasEnabled('director')) {
    $this->provideConfigTab('director', [
        'title' => $this->translate('Configure the director map datatype'),
        'label' => $this->translate('Director'),
        'url'   => 'config/director'
    ]);
}
