<?php

use OpenCage\Loader\CompatLoader;

// IcingaDB hooks
$this->provideHook('icingadb/IcingadbSupport');
$this->provideHook('icingadb/HostActions');
$this->provideHook('icingadb/ServiceActions');

// Cube integration (IcingaDB version, Cube 1.2+)
$this->provideHook('cube/IcingaDbActions', 'IcingaDbCubeLinks');

// OpenCage Geocoder library
require_once __DIR__ . '/library/vendor/OpenCage/Loader/CompatLoader.php';
CompatLoader::delegateLoadingToIcingaWeb($this->app);
