<?php

declare(strict_types=1);

namespace Icinga\Module\Map\ProvidedHook;

use Icinga\Module\Cube\Cube;
use Icinga\Module\Cube\Hook\ActionsHook;
use Icinga\Module\Cube\IcingaDb\IcingaDbHostStatusCube;
use Icinga\Web\View;

final class IcingaDbCubeLinks extends ActionsHook
{
    public function prepareActionLinks(Cube $cube, View $view): void
    {
        if (!$cube instanceof IcingaDbHostStatusCube) {
            return;
        }

        $vars = ['objectType' => 'host'];
        
        foreach ($cube->getSlices() as $dimension => $slice) {
            $vars["host.vars.{$dimension}"] = trim($slice, '"');
        }

        $this->addActionLink(
            $this->makeUrl('map', $vars),
            $view->translate('Show on map'),
            $view->translate('This shows all matching hosts and their current state on the map module'),
            'globe'
        );
    }
}
