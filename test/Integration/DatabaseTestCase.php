<?php

declare(strict_types=1);

/**
 * Base test case for integration tests requiring database access.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

namespace Horde\Db\Test\Integration;

use PHPUnit\Framework\TestCase;
use Exception;
use PDO;
use PDOException;

/**
 * Base class for database integration tests.
 *
 * Provides common functionality for tests that require real database connections.
 */
abstract class DatabaseTestCase extends TestCase
{
    /**
     * Get MySQL configuration from environment.
     *
     * @return array
     */
    protected function getMysqlConfig(): array
    {
        return [
            'adapter' => 'mysqli',
            'host' => getenv('DB_MYSQL_HOST') ?: 'localhost',
            'port' => (int) (getenv('DB_MYSQL_PORT') ?: 3306),
            'username' => getenv('DB_MYSQL_USER') ?: 'root',
            'password' => getenv('DB_MYSQL_PASS') ?: '',
            'database' => getenv('DB_MYSQL_DB') ?: 'horde_test',
            'charset' => 'utf8mb4',
        ];
    }

    /**
     * Get PostgreSQL configuration from environment.
     *
     * @return array
     */
    protected function getPostgresConfig(): array
    {
        return [
            'adapter' => 'pdo_pgsql',
            'host' => getenv('DB_PGSQL_HOST') ?: 'localhost',
            'port' => (int) (getenv('DB_PGSQL_PORT') ?: 5432),
            'username' => getenv('DB_PGSQL_USER') ?: 'postgres',
            'password' => getenv('DB_PGSQL_PASS') ?: '',
            'database' => getenv('DB_PGSQL_DB') ?: 'horde_test',
        ];
    }

    /**
     * Get Oracle configuration from environment.
     *
     * @return array
     */
    protected function getOracleConfig(): array
    {
        return [
            'adapter' => 'oci8',
            'host' => getenv('DB_ORACLE_HOST') ?: 'localhost',
            'port' => (int) (getenv('DB_ORACLE_PORT') ?: 1521),
            'username' => getenv('DB_ORACLE_USER') ?: 'system',
            'password' => getenv('DB_ORACLE_PASS') ?: 'oracle',
            'database' => getenv('DB_ORACLE_DB') ?: 'XE',
        ];
    }

    /**
     * Get SQLite configuration (in-memory).
     *
     * @return array
     */
    protected function getSqliteConfig(): array
    {
        return [
            'adapter' => 'pdo_sqlite',
            'database' => ':memory:',
        ];
    }

    /**
     * Check if MySQL is available.
     *
     * @return bool
     */
    protected function isMysqlAvailable(): bool
    {
        if (!extension_loaded('mysqli')) {
            return false;
        }

        $config = $this->getMysqlConfig();
        $conn = @mysqli_connect(
            $config['host'],
            $config['username'],
            $config['password'],
            '',
            $config['port']
        );

        if (!$conn) {
            return false;
        }

        mysqli_close($conn);
        return true;
    }

    /**
     * Check if PostgreSQL is available.
     *
     * @return bool
     */
    protected function isPostgresAvailable(): bool
    {
        if (!extension_loaded('pdo_pgsql')) {
            return false;
        }

        $config = $this->getPostgresConfig();
        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $config['host'],
                $config['port'],
                'postgres'
            );
            $pdo = new PDO($dsn, $config['username'], $config['password']);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Check if Oracle is available.
     *
     * @return bool
     */
    protected function isOracleAvailable(): bool
    {
        if (!extension_loaded('oci8')) {
            return false;
        }

        $config = $this->getOracleConfig();
        $conn = @oci_connect(
            $config['username'],
            $config['password'],
            $config['host'] . ':' . $config['port'] . '/' . $config['database']
        );

        if (!$conn) {
            return false;
        }

        oci_close($conn);
        return true;
    }

    /**
     * Skip test if database is not available.
     *
     * @param string $database Database type (mysql, postgres, oracle, sqlite)
     */
    protected function requireDatabase(string $database): void
    {
        $method = 'is' . ucfirst($database) . 'Available';
        if (method_exists($this, $method) && !$this->$method()) {
            $this->markTestSkipped(ucfirst($database) . ' database is not available');
        }
    }

    /**
     * Drop test tables if they exist.
     *
     * @param mixed $conn Database connection
     * @param array $tables Table names to drop
     */
    protected function dropTables($conn, array $tables): void
    {
        foreach ($tables as $table) {
            try {
                $conn->execute("DROP TABLE IF EXISTS $table");
            } catch (Exception $e) {
                // Ignore errors - table might not exist
            }
        }
    }
}
