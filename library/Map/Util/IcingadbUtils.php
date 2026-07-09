<?php

declare(strict_types=1);

namespace Icinga\Module\Map\Util;

use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Common\Backend;
use ipl\Sql\Connection;

/**
 * Utility class for IcingaDB integration
 * 
 * Uses the Backend singleton from icingadb-web for database connection,
 * ensuring connection reuse and consistent configuration.
 */
final class IcingadbUtils
{
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

    /**
     * Get the IcingaDB database connection
     * 
     * Delegates to Backend::getDb() for connection reuse and
     * proper configuration (SQL modes, PostgreSQL support, etc.)
     */
    public function getDb(): Connection
    {
        return Backend::getDb();
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
