<?php

/**
 * Copyright 2006-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

declare(strict_types=1);

namespace Horde\Db\Adapter;

use InvalidArgumentException;

/**
 * Interface for database adapters that support server capability detection.
 *
 * Adapters implementing this interface can provide information about the
 * database server version and supported features. This allows application
 * code to conditionally use database-specific features based on server
 * capabilities.
 *
 * Example usage:
 * <code>
 * if ($adapter instanceof CapabilityDetection) {
 *     $info = $adapter->getServerCapabilities();
 *     if ($info->supportsJSON()) {
 *         // Use JSON features
 *     }
 * }
 * </code>
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 * @since    Horde_Db 3.0.0
 */
interface CapabilityDetection
{
    /**
     * Get server capability information.
     *
     * Returns an object containing server version information and methods
     * to check for supported features. The returned object type is
     * database-specific:
     * - MySQL/MariaDB: Mysql\ServerInfo
     * - PostgreSQL: Postgresql\ServerInfo (future)
     * - SQLite: Sqlite\ServerInfo (future)
     *
     * The capability object is cached after first call and reused for
     * subsequent requests.
     *
     * @return object  Database-specific server capability object with
     *                 feature detection methods.
     */
    public function getServerCapabilities(): object;

    /**
     * Check if a specific capability is supported.
     *
     * This is a convenience method that delegates to the appropriate
     * method on the server capability object. The capability name should
     * match a method name on the capability object (case-insensitive).
     *
     * Common capabilities (MySQL/MariaDB):
     * - 'json' - JSON data type and functions
     * - 'cte' - Common Table Expressions (WITH clause)
     * - 'window_functions' - Window functions (OVER, PARTITION BY)
     * - 'check_constraints' - CHECK constraints
     * - 'invisible_columns' - Invisible/hidden columns
     * - 'descending_indexes' - Descending index support
     * - 'instant_add_column' - Fast ADD COLUMN without table rebuild
     * - 'rename_column' - RENAME COLUMN syntax
     * - 'generated_columns' - Generated/computed columns
     * - 'spatial_indexes' - Spatial indexes on InnoDB
     *
     * @param string $capability  Capability name (e.g., 'json', 'cte').
     *
     * @return bool  True if the capability is supported, false otherwise.
     *
     * @throws InvalidArgumentException  If the capability name is not recognized.
     */
    public function hasCapability(string $capability): bool;
}
