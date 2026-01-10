<?php

declare(strict_types=1);

namespace Icinga\Module\Map\Util;

use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Database;

final class IcingadbUtils
{
    use Database;
    use Auth;

    private static ?self $instance = null;

    private function __construct()
    {
        // Private constructor for singleton
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    private function __clone(): void
    {
        // Prevent cloning
    }

    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize singleton');
    }
}
