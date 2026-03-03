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

namespace Horde\Db\Adapter\Mysql;

/**
 * MySQL/MariaDB server information and feature detection.
 *
 * Detects database server type, version, and supported features based on
 * version numbers rather than runtime feature testing.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 * @since    Horde_Db 3.0.0
 */
class ServerInfo
{
    /**
     * Full version string from server.
     */
    public readonly string $versionString;

    /**
     * True if this is MariaDB, false if MySQL.
     */
    public readonly bool $isMariaDB;

    /**
     * Major version number.
     */
    public readonly int $majorVersion;

    /**
     * Minor version number.
     */
    public readonly int $minorVersion;

    /**
     * Patch version number.
     */
    public readonly int $patchVersion;

    /**
     * Numeric version from server (e.g., 100117 for 10.1.17).
     */
    public readonly int $numericVersion;

    /**
     * Constructor.
     *
     * @param string $versionString  Version string from SELECT VERSION() or
     *                               mysqli::server_info.
     * @param int $numericVersion    Numeric version from mysqli::server_version.
     */
    public function __construct(string $versionString, int $numericVersion)
    {
        $this->versionString = $versionString;
        $this->numericVersion = $numericVersion;
        $this->isMariaDB = stripos($versionString, 'mariadb') !== false;

        // Parse semantic version from string
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $versionString, $matches)) {
            $this->majorVersion = (int)$matches[1];
            $this->minorVersion = (int)$matches[2];
            $this->patchVersion = (int)$matches[3];
        } else {
            // Fallback: extract from numeric version
            $this->majorVersion = (int)floor($numericVersion / 10000);
            $this->minorVersion = (int)floor(($numericVersion % 10000) / 100);
            $this->patchVersion = $numericVersion % 100;
        }
    }

    /**
     * Create ServerInfo from mysqli connection.
     *
     * @param \mysqli $connection  Active mysqli connection.
     *
     * @return self
     */
    public static function fromMysqli(\mysqli $connection): self
    {
        return new self($connection->server_info, $connection->server_version);
    }

    /**
     * Create ServerInfo from PDO connection.
     *
     * @param \PDO $connection  Active PDO connection.
     *
     * @return self
     */
    public static function fromPDO(\PDO $connection): self
    {
        $versionString = $connection->getAttribute(\PDO::ATTR_SERVER_VERSION);

        // PDO doesn't provide numeric version directly, so parse it
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $versionString, $m)) {
            $numericVersion = (int)$m[1] * 10000 + (int)$m[2] * 100 + (int)$m[3];
        } else {
            $numericVersion = 0;
        }

        return new self($versionString, $numericVersion);
    }

    /**
     * Check if version is at least the specified version.
     *
     * @param int $major  Major version.
     * @param int $minor  Minor version (optional).
     * @param int $patch  Patch version (optional).
     *
     * @return bool
     */
    public function isAtLeast(int $major, int $minor = 0, int $patch = 0): bool
    {
        if ($this->majorVersion > $major) {
            return true;
        }
        if ($this->majorVersion < $major) {
            return false;
        }

        if ($this->minorVersion > $minor) {
            return true;
        }
        if ($this->minorVersion < $minor) {
            return false;
        }

        return $this->patchVersion >= $patch;
    }

    /**
     * Support for JSON data type and functions.
     *
     * MySQL 5.7.8+, MariaDB 10.2.7+
     *
     * @return bool
     */
    public function supportsJSON(): bool
    {
        if ($this->isMariaDB) {
            return $this->isAtLeast(10, 2, 7);
        }
        return $this->isAtLeast(5, 7, 8);
    }

    /**
     * Support for Common Table Expressions (WITH clause).
     *
     * MySQL 8.0+, MariaDB 10.2.1+
     *
     * @return bool
     */
    public function supportsCTE(): bool
    {
        if ($this->isMariaDB) {
            return $this->isAtLeast(10, 2, 1);
        }
        return $this->isAtLeast(8, 0);
    }

    /**
     * Support for Window Functions (OVER, PARTITION BY).
     *
     * MySQL 8.0+, MariaDB 10.2+
     *
     * @return bool
     */
    public function supportsWindowFunctions(): bool
    {
        if ($this->isMariaDB) {
            return $this->isAtLeast(10, 2);
        }
        return $this->isAtLeast(8, 0);
    }

    /**
     * Support for CHECK constraints.
     *
     * MySQL 8.0.16+, MariaDB 10.2.1+
     *
     * @return bool
     */
    public function supportsCheckConstraints(): bool
    {
        if ($this->isMariaDB) {
            return $this->isAtLeast(10, 2, 1);
        }
        return $this->isAtLeast(8, 0, 16);
    }

    /**
     * Support for invisible columns.
     *
     * MySQL 8.0.23+, MariaDB 10.3+
     *
     * @return bool
     */
    public function supportsInvisibleColumns(): bool
    {
        if ($this->isMariaDB) {
            return $this->isAtLeast(10, 3);
        }
        return $this->isAtLeast(8, 0, 23);
    }

    /**
     * Support for descending indexes.
     *
     * MySQL 8.0+, MariaDB 10.8+
     *
     * @return bool
     */
    public function supportsDescendingIndexes(): bool
    {
        if ($this->isMariaDB) {
            return $this->isAtLeast(10, 8);
        }
        return $this->isAtLeast(8, 0);
    }

    /**
     * Support for instant ADD COLUMN.
     *
     * MySQL 8.0.12+, MariaDB 10.3+
     *
     * @return bool
     */
    public function supportsInstantAddColumn(): bool
    {
        if ($this->isMariaDB) {
            return $this->isAtLeast(10, 3);
        }
        return $this->isAtLeast(8, 0, 12);
    }

    /**
     * Support for RENAME COLUMN.
     *
     * MySQL 8.0+, MariaDB 10.5.2+
     *
     * @return bool
     */
    public function supportsRenameColumn(): bool
    {
        if ($this->isMariaDB) {
            return $this->isAtLeast(10, 5, 2);
        }
        return $this->isAtLeast(8, 0);
    }

    /**
     * Support for generated/computed columns.
     *
     * MySQL 5.7+, MariaDB 5.2+
     *
     * @return bool
     */
    public function supportsGeneratedColumns(): bool
    {
        if ($this->isMariaDB) {
            return $this->isAtLeast(5, 2);
        }
        return $this->isAtLeast(5, 7);
    }

    /**
     * Support for spatial indexes on InnoDB tables.
     *
     * MySQL 5.7+, MariaDB 10.2.2+
     *
     * @return bool
     */
    public function supportsSpatialIndexes(): bool
    {
        if ($this->isMariaDB) {
            return $this->isAtLeast(10, 2, 2);
        }
        return $this->isAtLeast(5, 7);
    }

    /**
     * Get a human-readable description of this server.
     *
     * @return string
     */
    public function getDescription(): string
    {
        $type = $this->isMariaDB ? 'MariaDB' : 'MySQL';
        return sprintf(
            '%s %d.%d.%d',
            $type,
            $this->majorVersion,
            $this->minorVersion,
            $this->patchVersion
        );
    }
}
