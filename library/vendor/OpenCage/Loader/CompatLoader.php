<?php

declare(strict_types=1);

namespace OpenCage\Loader;

use Icinga\Application\ApplicationBootstrap;

final class CompatLoader
{
    public static function delegateLoadingToIcingaWeb(ApplicationBootstrap $app): void
    {
        $app->getLoader()->registerNamespace('OpenCage', dirname(__DIR__));
    }
}
