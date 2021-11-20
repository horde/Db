<?php
/**
 * Copyright 2007 Maintainable Software, LLC
 * Copyright 2006-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Migration
 */

namespace Horde\Db\Migration;

use Horde\Db\Adapter;
use Horde_Log_Logger;
use Horde_Support_Stub;
use Horde_Support_Inflector;
use RegexIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use RecursiveRegexIterator;

/**
 *
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @category   Horde
 * @copyright  2007 Maintainable Software, LLC
 * @copyright  2006-2021 Horde LLC
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Migration
 */
class Migrator
{
    /**
     * @var string
     */
    protected $direction = null;

    /**
     * @var string
     */
    protected $migrationsPath = null;

    /**
     * @var int
     */
    protected $targetVersion = null;

    /**
     * @var string
     */
    protected $schemaTableName = 'schema_info';

    /**
     * @var Horde_Log_Logger|null
     */
    protected $logger;
    protected $inflector;
    protected Adapter $connection;

    /**
     * Constructor.
     *
     * @param Adapter $connection  A DB connection object.
     * @param Horde_Log_Logger $logger      A logger object.
     * @param array $options                Additional options for the migrator:
     *                                      - migrationsPath: directory with the
     *                                        migration files.
     *                                      - schemaTableName: table for storing
     *                                        the schema version.
     *
     * @throws MigrationException
     */
    public function __construct(
        Adapter $connection,
        Horde_Log_Logger $logger = null,
        array $options = []
    ) {
        if (!$connection->supportsMigrations()) {
            throw new MigrationException('This database does not yet support migrations');
        }

        $this->connection = $connection;
        $this->logger = $logger ? $logger : new Horde_Support_Stub();
        $this->inflector = new Horde_Support_Inflector();
        if (isset($options['migrationsPath'])) {
            $this->migrationsPath = $options['migrationsPath'];
        }
        if (isset($options['schemaTableName'])) {
            $this->schemaTableName = $options['schemaTableName'];
        }

        $this->initializeSchemaInformation();
    }

    /**
     * @param string $targetVersion
     */
    public function migrate($targetVersion = null)
    {
        $currentVersion = $this->getCurrentVersion();

        if ($targetVersion == null || $currentVersion < $targetVersion) {
            $this->up($targetVersion);
        } elseif ($currentVersion > $targetVersion) {
            // migrate down
            $this->down($targetVersion);
        }
    }

    /**
     * @param string $targetVersion
     */
    public function up($targetVersion = null)
    {
        $this->targetVersion = $targetVersion;
        $this->direction = 'up';
        $this->doMigrate();
    }

    /**
     * @param string $targetVersion
     */
    public function down($targetVersion = null)
    {
        $this->targetVersion = $targetVersion;
        $this->direction = 'down';
        $this->doMigrate();
    }

    /**
     * @return int
     */
    public function getCurrentVersion()
    {
        return in_array($this->schemaTableName, $this->connection->tables())
            ? $this->connection->selectValue('SELECT version FROM ' . $this->schemaTableName)
            : 0;
    }

    /**
     * @return int
     */
    public function getTargetVersion()
    {
        $migrations = [];
        foreach ($this->getMigrationFiles() as $migrationFile) {
            list($version, $name) = $this->getMigrationVersionAndName($migrationFile);
            $this->assertUniqueMigrationVersion($migrations, $version);
            $migrations[$version] = $name;
        }

        // Sort by version.
        uksort($migrations, 'strnatcmp');

        return key(array_reverse($migrations, true));
    }

    /**
     * @param string $migrationsPath  Path to migration files.
     */
    public function setMigrationsPath($migrationsPath)
    {
        $this->migrationsPath = $migrationsPath;
    }

    /**
     * @param Horde_Log_Logger $logger
     */
    public function setLogger(Horde_Log_Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @param Horde_Support_Inflector $inflector
     */
    public function setInflector(Horde_Support_Inflector $inflector)
    {
        $this->inflector = $inflector;
    }

    /**
     * Performs the migration.
     */
    protected function doMigrate()
    {
        foreach ($this->getMigrationClasses() as $migration) {
            if ($this->hasReachedTargetVersion($migration->version)) {
                $this->logger->info('Reached target version: ' . $this->targetVersion);
                return;
            }
            if ($this->isIrrelevantMigration($migration->version)) {
                continue;
            }

            $this->logger->info('Migrating ' . ($this->direction == 'up' ? 'to ' : 'from ') . get_class($migration) . ' (' . $migration->version . ')');
            $migration->migrate($this->direction);
            $this->setSchemaVersion($migration->version);
        }
    }

    /**
     * @return array
     */
    protected function getMigrationClasses()
    {
        $migrations = [];
        foreach ($this->getMigrationFiles() as $migrationFile) {
            require_once $migrationFile;
            list($version, $name) = $this->getMigrationVersionAndName($migrationFile);
            $this->assertUniqueMigrationVersion($migrations, $version);
            $migrations[$version] = $this->getMigrationClass($name, $version);
        }

        // Sort by version.
        uksort($migrations, 'strnatcmp');
        $sorted = array_values($migrations);

        return $this->isDown() ? array_reverse($sorted) : $sorted;
    }

    /**
     * @param array   $migrations
     * @param integer $version
     *
     * @throws MigrationException
     */
    protected function assertUniqueMigrationVersion($migrations, $version)
    {
        if (isset($migrations[$version])) {
            throw new MigrationException('Multiple migrations have the version number ' . $version);
        }
    }

    /**
     * Returns the list of migration files.
     *
     * @return array
     */
    protected function getMigrationFiles()
    {
        return array_keys(
            iterator_to_array(
                new RegexIterator(
                    new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator(
                            $this->migrationsPath
                        )
                    ),
                    '/' . preg_quote(DIRECTORY_SEPARATOR, '/') . '\d+_.*\.php$/',
                    RecursiveRegexIterator::MATCH,
                    RegexIterator::USE_KEY
                )
            )
        );
    }

    /**
     * Actually returns object, and not class.
     *
     * @param string  $migrationName
     * @param integer $version
     *
     * @return  Base
     */
    protected function getMigrationClass($migrationName, $version)
    {
        $className = $this->inflector->camelize($migrationName);
        $class = new $className($this->connection, $version);
        $class->setLogger($this->logger);

        return $class;
    }

    /**
     * @param string $migrationFile
     *
     * @return array  ($version, $name)
     */
    protected function getMigrationVersionAndName($migrationFile)
    {
        preg_match_all('/([0-9]+)_([_a-z0-9]*).php/', $migrationFile, $matches);
        return array($matches[1][0], $matches[2][0]);
    }

    /**
     * @TODO
     */
    protected function initializeSchemaInformation()
    {
        if (in_array($this->schemaTableName, $this->connection->tables())) {
            return;
        }
        $schemaTable = $this->connection->createTable($this->schemaTableName, array('autoincrementKey' => false));
        $schemaTable->column('version', 'integer');
        $schemaTable->end();
        $this->connection->insert('INSERT INTO ' . $this->schemaTableName . ' (version) VALUES (0)', null, null, null, 1);
    }

    /**
     * @param integer $version
     */
    protected function setSchemaVersion($version)
    {
        $version = $this->isDown() ? $version - 1 : $version;
        if ($version) {
            $sql = 'UPDATE ' . $this->schemaTableName . ' SET version = ' . (int)$version;
            $this->connection->update($sql);
        } else {
            $this->connection->dropTable($this->schemaTableName);
        }
    }

    /**
     * @return bool
     */
    protected function isUp()
    {
        return $this->direction == 'up';
    }

    /**
     * @return bool
     */
    protected function isDown()
    {
        return $this->direction == 'down';
    }

    /**
     * @return bool
     */
    protected function hasReachedTargetVersion($version)
    {
        if ($this->targetVersion === null) {
            return false;
        }

        return ($this->isUp()   && $version - 1 >= $this->targetVersion) ||
               ($this->isDown() && $version     <= $this->targetVersion);
    }

    /**
     * @param int $version
     *
     * @return  bool
     */
    protected function isIrrelevantMigration($version)
    {
        return ($this->isUp()   && $version <= self::getCurrentVersion()) ||
               ($this->isDown() && $version >  self::getCurrentVersion());
    }
}
