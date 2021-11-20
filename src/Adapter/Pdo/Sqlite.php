<?php
/**
 * Copyright 2007 Maintainable Software, LLC
 * Copyright 2008-2021 Horde LLC (http://www.horde.org/)
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
 * @subpackage Adapter
 */

namespace Horde\Db\Adapter\Pdo;

use Horde\Db\Adapter\Sqlite\Schema as SqliteSchema;
use Horde\Db\DbException;
use PDO;
use PDOException;
use Exception;

/**
 * PDO_SQLite Horde_Db_Adapter
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @category   Horde
 * @copyright  2007 Maintainable Software, LLC
 * @copyright  2008-2021 Horde LLC
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Adapter
 */
class Sqlite extends Base
{
    /**
     * @var string
     */
    protected $schemaClass = SqliteSchema::class;

    /**
     * SQLite version number
     * @var int
     */
    protected $sqliteVersion;

    /**
     * @return  string
     */
    public function adapterName()
    {
        return 'PDO_SQLite';
    }

    /**
     * @return  bool
     */
    public function supportsMigrations()
    {
        return true;
    }

    /**
     * Does this adapter support using DISTINCT within COUNT?  This is +true+
     * for all adapters except sqlite.
     *
     * @return  bool
     */
    public function supportsCountDistinct()
    {
        return $this->sqliteVersion >= '3.2.6';
    }

    /**
     * Does this adapter support using INTERVAL statements?  This is +true+
     * for all adapters except sqlite.
     *
     * @return bool
     */
    public function supportsInterval()
    {
        return false;
    }

    public function supportsAutoIncrement()
    {
        return $this->sqliteVersion >= '3.1.0';
    }


    /*##########################################################################
    # Connection Management
    ##########################################################################*/

    /**
     * Connect to the db.
     *
     * @throws DbException
     */
    public function connect()
    {
        if ($this->active) {
            return;
        }

        parent::connect();

        $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $this->lastQuery = $sql = 'PRAGMA full_column_names=0';
        $retval = $this->connection->exec($sql);
        if ($retval === false) {
            $error = $this->connection->errorInfo();
            throw new DbException($error[2]);
        }

        $this->lastQuery = $sql = 'PRAGMA short_column_names=1';
        $retval = $this->connection->exec($sql);
        if ($retval === false) {
            $error = $this->connection->errorInfo();
            throw new DbException($error[2]);
        }

        $this->lastQuery = $sql = 'SELECT sqlite_version(*)';
        $this->sqliteVersion = $this->selectValue($sql);
    }


    /*##########################################################################
    # Database Statements
    ##########################################################################*/

    /**
     * Executes the SQL statement in the context of this connection.
     *
     * @param   string  $sql
     * @param   mixed   $arg1  Either an array of bound parameters or a query name.
     * @param   string  $arg2  If $arg1 contains bound parameters, the query name.
     */
    public function execute($sql, $arg1=null, $arg2=null)
    {
        return $this->catchSchemaChanges('execute', array($sql, $arg1, $arg2));
    }

    /**
     * Begins the transaction (and turns off auto-committing).
     */
    public function beginDbTransaction()
    {
        return $this->catchSchemaChanges('beginDbTransaction');
    }

    /**
     * Commits the transaction (and turns on auto-committing).
     */
    public function commitDbTransaction()
    {
        return $this->catchSchemaChanges('commitDbTransaction');
    }

    /**
     * Rolls back the transaction (and turns on auto-committing). Must be
     * done if the transaction block raises an exception or returns false.
     */
    public function rollbackDbTransaction()
    {
        return $this->catchSchemaChanges('rollbackDbTransaction');
    }

    /**
     * SELECT ... FOR UPDATE is redundant since the table is locked.
     */
    public function addLock(&$sql, array $options = [])
    {
    }

    public function emptyInsertStatement($tableName)
    {
        return 'INSERT INTO '.$this->quoteTableName($tableName).' VALUES(NULL)';
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    protected function catchSchemaChanges($method, $args = [])
    {
        try {
            return call_user_func_array(array($this, "parent::$method"), $args);
        } catch (Exception $e) {
            if (preg_match('/database schema has changed/i', $e->getMessage())) {
                $this->reconnect();
                return call_user_func_array(array($this, "parent::$method"), $args);
            } else {
                throw $e;
            }
        }
    }

    protected function buildDsnString($params)
    {
        return 'sqlite:' . $params['dbname'];
    }

    /**
     * Parse configuration array into options for PDO constructor
     *
     * @throws  DbException
     * @return  array  [dsn, username, password]
     */
    protected function parseConfig()
    {
        // check required config keys are present
        if (empty($this->config['database']) && empty($this->config['dbname'])) {
            $msg = 'Either dbname or database is required';
            throw new DbException($msg);
        }

        // collect options to build PDO Data Source Name (DSN) string
        $dsnOpts = $this->config;
        unset($dsnOpts['adapter'], $dsnOpts['username'], $dsnOpts['password']);

        // return DSN and dummy user/pass for connection
        return array($this->buildDsnString($this->normalizeConfig($dsnOpts)), '', '');
    }
}
