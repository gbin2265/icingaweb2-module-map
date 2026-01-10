<?php

declare(strict_types=1);

namespace Icinga\Module\Map\ProvidedHook\Icingadb;

use Icinga\Module\Icingadb\Hook\HostActionsHook;
use Icinga\Module\Icingadb\Model\Host;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\Link;

final class HostActions extends HostActionsHook
{
    public function getActionsForObject(Host $host): array
    {
        if (!isset($host->vars['geolocation'])) {
            return [];
        }

        return [
            new Link(
                [new Icon('globe'), mt('map', 'Show on map')],
                'map?showHost=' . rawurlencode($host->name)
            )
        ];
    }
}
