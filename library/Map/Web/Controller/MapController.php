<?php

declare(strict_types=1);

namespace Icinga\Module\Map\Web\Controller;

use Icinga\Module\Map\Util\IcingadbUtils;
use ipl\Web\Compat\CompatController;

abstract class MapController extends CompatController
{
    protected IcingadbUtils $icingadbUtils;

    /** @var string Regex pattern for validating coordinates (lat,lng format) */
    protected const COORDINATE_PATTERN = '/^(-?\d+(?:\.\d+)?),\s*(-?\d+(?:\.\d+)?)$/';

    /** For backwards compatibility */
    protected string $coordinatePattern = self::COORDINATE_PATTERN;

    public function init(): void
    {
        parent::init();
        $this->icingadbUtils = IcingadbUtils::getInstance();
    }
}
