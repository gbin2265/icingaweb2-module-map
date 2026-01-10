<?php

declare(strict_types=1);

namespace Icinga\Module\Map\ProvidedHook\Icingadb;

use Icinga\Module\Icingadb\Hook\ServiceActionsHook;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\Link;

final class ServiceActions extends ServiceActionsHook
{
    public function getActionsForObject(Service $service): array
    {
        if (!isset($service->vars['geolocation'])) {
            return [];
        }

        return [
            new Link(
                [new Icon('globe'), mt('map', 'Show on map')],
                'map?showHost=' . rawurlencode("{$service->host->name}!{$service->name}")
            )
        ];
    }
}
