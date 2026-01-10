<?php

use OpenCage\Loader\CompatLoader;

// IcingaDB hooks
$this->provideHook('icingadb/IcingadbSupport');
$this->provideHook('icingadb/HostActions');
$this->provideHook('icingadb/ServiceActions');

// Cube integration (IcingaDB version)
$this->provideHook('cube/Actions', 'IcingaDbCubeLinks');

// OpenCage Geocoder library
require_once __DIR__ . '/library/vendor/OpenCage/Loader/CompatLoader.php';
CompatLoader::delegateLoadingToIcingaWeb($this->app);
