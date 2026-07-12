<?php

declare(strict_types=1);

namespace Icinga\Module\Map\ProvidedHook;

use Icinga\Module\Cube\Hook\IcingaDbActionsHook;
use Icinga\Module\Cube\IcingaDb\IcingaDbCube;
use Icinga\Module\Cube\IcingaDb\IcingaDbHostStatusCube;

/**
 * Cube integration for the map module
 *
 * Implements Cube 1.2+'s IcingaDbActionsHook (registered as 'cube/IcingaDbActions').
 * Note: since Cube 1.2 the slice dimension names already contain the
 * 'host.vars.' prefix, so they are passed through as-is.
 */
final class IcingaDbCubeLinks extends IcingaDbActionsHook
{
    public function createActionLinks(IcingaDbCube $cube): void
    {
        if (!$cube instanceof IcingaDbHostStatusCube) {
            return;
        }

        $params = ['objectType' => 'host'];

        foreach ($cube->getSlices() as $dimension => $slice) {
            // Dimension already contains the 'host.vars.' prefix (Cube 1.2+)
            $params[(string) $dimension] = trim((string) $slice, '"');
        }

        $this->addActionLink(
            $this->makeUrl('map', $params),
            t('Show on map'),
            t('This shows all matching hosts and their current state on the map module'),
            'globe'
        );
    }
}
