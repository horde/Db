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
 * @subpackage Adapter
 */

namespace Horde\Db\Adapter;

use Horde\Db\Adapter\Mysql\Schema;
use Horde\Db\DbException;
use Horde\Db\Adapter\Mysqli\Result;
//use \mysqli; // Cannot import mysqli as it would clash with class name;
use mysqli_result;
use Horde_Support_Timer;

/**
 * MySQL Improved Horde_Db_Adapter
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @category   Horde
 * @copyright  2007 Maintainable Software, LLC
 * @copyright  2006-2021 Horde LLC
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Adapter
 */
class Mysqli extends Base
{
    /**
     * Last auto-generated insert_id
     * @var int
     */
    protected $insertId;

    /**
     * @var string
     */
    protected $schemaClass = Schema::class;

    /**
     * @var bool
     */
    protected $hasMysqliFetchAll = false;


    /*##########################################################################
    # Public
    ##########################################################################*/

    /**
     * Returns the human-readable name of the adapter.  Use mixed case - one
     * can always use downcase if needed.
     *
     * @return  string
     */
    public function adapterName()
    {
        return 'MySQLi';
    }

    /**
     * Does this adapter support migrations?  Backend specific, as the
     * abstract adapter always returns +false+.
     *
     * @return  boolean
     */
    public function supportsMigrations()
    {
        return true;
    }


    /*##########################################################################
    # Connection Management
    ##########################################################################*/

    /**
     * Connect to the db
     *
     * MySQLi can connect using SSL if $config contains an 'ssl' sub-array
     * containing the following keys:
     *     + key      The path to the key file.
     *     + cert     The path to the certificate file.
     *     + ca       The path to the certificate authority file.
     *     + capath   The path to a directory that contains trusted SSL
     *                CA certificates in pem format.
     *     + cipher   The list of allowable ciphers for SSL encryption.
     *
     * Example of how to connect using SSL:
     * <code>
     * $config = array(
     *     'username' => 'someuser',
     *     'password' => 'apasswd',
     *     'hostspec' => 'localhost',
     *     'database' => 'thedb',
     *     'ssl'      => array(
     *         'key'      => 'client-key.pem',
     *         'cert'     => 'client-cert.pem',
     *         'ca'       => 'cacert.pem',
     *         'capath'   => '/path/to/ca/dir',
     *         'cipher'   => 'AES',
     *     ),
     * );
     *
     * $db = new Horde_Db_Adapter_Mysqli($config);
     * </code>
     */
    public function connect()
    {
        if ($this->active) {
            return;
        }

        $config = $this->parseConfig();

        if (!empty($config['ssl'])) {
            $mysqli = mysqli_init();
            $mysqli->ssl_set(
                empty($config['ssl']['key']) ? null : $config['ssl']['key'],
                empty($config['ssl']['cert']) ? null : $config['ssl']['cert'],
                empty($config['ssl']['ca']) ? null : $config['ssl']['ca'],
                empty($config['ssl']['capath']) ? null : $config['ssl']['capath'],
                empty($config['ssl']['cipher']) ? null : $config['ssl']['cipher']
            );
            $mysqli->real_connect(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['dbname'],
                $config['port'],
                $config['socket'],
                MYSQLI_CLIENT_SSL
            );
        } else {
            $mysqli = new \mysqli(
                $config['host'],
                $config['username'],
                $config['password'],
                $config['dbname'],
                $config['port'],
                $config['socket']
            );
        }
        if (mysqli_connect_errno()) {
            throw new DbException('Connect failed: (' . mysqli_connect_errno() . ') ' . mysqli_connect_error(), mysqli_connect_errno());
        }

        // If supported, request real datatypes from MySQL instead of returning
        // everything as a string.
        if (defined('MYSQLI_OPT_INT_AND_FLOAT_NATIVE')) {
            $mysqli->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);
        }

        $this->connection = $mysqli;
        $this->active     = true;

        // Set the default charset. http://dev.mysql.com/doc/refman/5.1/en/charset-connection.html
        if (!empty($config['charset'])) {
            $this->schema->setCharset($config['charset']);
        }

        $this->hasMysqliFetchAll = function_exists('mysqli_fetch_all');
    }

    /**
     * Disconnect from db
     */
    public function disconnect()
    {
        if ($this->connection) {
            $this->connection->close();
        }
        parent::disconnect();
    }

    /**
     * Check if the connection is active
     *
     * @return  boolean
     */
    public function isActive()
    {
        $this->lastQuery = 'SELECT 1';
        return isset($this->connection) && $this->connection->query('SELECT 1');
    }


    /*##########################################################################
    # Quoting
    ##########################################################################*/

    /**
     * Quotes a string, escaping any ' (single quote) and \ (backslash)
     * characters..
     *
     * @param   string  $string
     * @return  string
     */
    public function quoteString($string)
    {
        return "'".$this->connection->real_escape_string($string)."'";
    }


    /*##########################################################################
    # Database Statements
    ##########################################################################*/

    /**
     * Returns an array of records with the column names as keys, and
     * column values as values.
     *
     * @param   string  $sql
     * @param   mixed   $arg1  Either an array of bound parameters or a query name.
     * @param   string  $arg2  If $arg1 contains bound parameters, the query name.
     * @return  Result
     */
    public function select($sql, $arg1=null, $arg2=null)
    {
        return new Result($this, $sql, $arg1, $arg2);
    }

    /**
     * Returns an array of record hashes with the column names as keys and
     * column values as values.
     *
     * @param   string  $sql
     * @param   mixed   $arg1  Either an array of bound parameters or a query name.
     * @param   string  $arg2  If $arg1 contains bound parameters, the query name.
     */
    public function selectAll($sql, $arg1=null, $arg2=null)
    {
        $result = $this->execute($sql, $arg1, $arg2);
        if ($this->hasMysqliFetchAll) {
            return $result->fetch_all(MYSQLI_ASSOC);
        } else {
            $rows = [];
            if ($result) {
                while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }

    /**
     * Returns a record hash with the column names as keys and column values as
     * values.
     *
     * @param string $sql   A query.
     * @param mixed  $arg1  Either an array of bound parameters or a query name.
     * @param string $arg2  If $arg1 contains bound parameters, the query name.
     *
     * @return array|boolean  A record hash or false if no record found.
     */
    public function selectOne($sql, $arg1 = null, $arg2 = null)
    {
        $result = $this->execute($sql, $arg1, $arg2);
        $result = $result ? $result->fetch_array(MYSQLI_ASSOC) : [];
        return is_null($result) ? false : $result;
    }

    /**
     * Returns a single value from a record
     *
     * @param   string  $sql
     * @param   mixed   $arg1  Either an array of bound parameters or a query name.
     * @param   string  $arg2  If $arg1 contains bound parameters, the query name.
     * @return  string
     */
    public function selectValue($sql, $arg1=null, $arg2=null)
    {
        $result = $this->selectOne($sql, $arg1, $arg2);
        return $result ? current($result) : null;
    }

    /**
     * Returns an array of the values of the first column in a select:
     *   select_values("SELECT id FROM companies LIMIT 3") => [1,2,3]
     *
     * @param   string  $sql
     * @param   mixed   $arg1  Either an array of bound parameters or a query name.
     * @param   string  $arg2  If $arg1 contains bound parameters, the query name.
     */
    public function selectValues($sql, $arg1=null, $arg2=null)
    {
        $values = [];
        $result = $this->execute($sql, $arg1, $arg2);
        if ($result) {
            while ($row = $result->fetch_row()) {
                $values[] = $row[0];
            }
        }
        return $values;
    }

    /**
     * Executes the SQL statement in the context of this connection.
     *
     * @deprecated  Deprecated for external usage. Use select() instead.
     *
     * @param string $sql   SQL statement.
     * @param mixed $arg1   Either an array of bound parameters or a query
     *                      name.
     * @param string $arg2  If $arg1 contains bound parameters, the query
     *                      name.
     *
     * @return mysqli_result
     * @throws DbException
     */
    public function execute($sql, $arg1=null, $arg2=null)
    {
        if (is_array($arg1)) {
            $query = $this->replaceParameters($sql, $arg1);
            $name = $arg2;
        } else {
            $name = $arg1;
            $query = $sql;
            $arg1 = [];
        }

        $t = new Horde_Support_Timer();
        $t->push();

        $this->lastQuery = $query;
        $stmt = $this->connection->query($query);
        if (!$stmt) {
            $this->logInfo($sql, $arg1, $name);
            $this->logError($query, 'QUERY FAILED: ' . $this->connection->error);
            throw new DbException(
                'QUERY FAILED: ' . $this->connection->error . "\n\n" . $query,
                $this->errorCode($this->connection->sqlstate, $this->connection->errno)
            );
        }

        $this->logInfo($sql, $arg1, $name, $t->pop());
        //@TODO if ($this->connection->info) $this->loginfo($sql, $this->connection->info);
        //@TODO also log warnings? http://php.net/mysqli.warning-count and http://php.net/mysqli.get-warnings

        $this->rowCount = $this->connection->affected_rows;
        $this->insertId = $this->connection->insert_id;
        return $stmt;
    }

    /**
     * Returns the last auto-generated ID from the affected table.
     *
     * @param   string  $sql
     * @param   mixed   $arg1  Either an array of bound parameters or a query name.
     * @param   string  $arg2  If $arg1 contains bound parameters, the query name.
     * @param   string  $pk
     * @param   int     $idValue
     * @param   string  $sequenceName
     */
    public function insert($sql, $arg1=null, $arg2=null, $pk=null, $idValue=null, $sequenceName=null)
    {
        $this->execute($sql, $arg1, $arg2);
        return isset($idValue) ? $idValue : $this->insertId;
    }

    /**
     * Begins the transaction (and turns off auto-committing).
     */
    public function beginDbTransaction()
    {
        $this->connection->autocommit(false);
        $this->transactionStarted++;
    }

    /**
     * Commits the transaction (and turns on auto-committing).
     */
    public function commitDbTransaction()
    {
        $this->transactionStarted--;
        if (!$this->transactionStarted) {
            $this->connection->commit();
            $this->connection->autocommit(true);
        }
    }

    /**
     * Rolls back the transaction (and turns on auto-committing). Must be
     * done if the transaction block raises an exception or returns false.
     */
    public function rollbackDbTransaction()
    {
        if (!$this->transactionStarted) {
            return;
        }

        $this->connection->rollback();
        $this->transactionStarted = 0;
        $this->connection->autocommit(true);
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    /**
     * Return a standard error code
     *
     * @param   string   $sqlstate
     * @param   int  $errno
     * @return  int
     */
    protected function errorCode($sqlstate, $errno)
    {
        /*@TODO do something with standard sqlstate vs. MySQL error codes vs. whatever else*/
        return $errno;
    }

    /**
     * Parse configuration array into options for MySQLi constructor.
     *
     * @throws  DbException
     * @return  array  [host, username, password, dbname, port, socket]
     */
    protected function parseConfig()
    {
        $this->checkRequiredConfig(array('username'));

        $rails2mysqli = array('database' => 'dbname');
        foreach ($rails2mysqli as $from => $to) {
            if (isset($this->config[$from])) {
                $this->config[$to] = $this->config[$from];
                unset($this->config[$from]);
            }
        }

        if (!empty($this->config['host']) &&
            $this->config['host'] == 'localhost') {
            $this->config['host'] = '127.0.0.1';
        }

        if (!empty($this->config['host']) && !empty($this->config['socket'])) {
            throw new DbException('Can only specify host or socket, not both');
        }

        if (isset($this->config['port'])) {
            if (empty($this->config['host'])) {
                throw new DbException('Host is required if port is specified');
            }
        }

        $config = $this->config;

        if (!isset($config['host'])) {
            $config['host'] = null;
        }
        if (!isset($config['username'])) {
            $config['username'] = null;
        }
        if (!isset($config['password'])) {
            $config['password'] = null;
        }
        if (!isset($config['dbname'])) {
            $config['dbname'] = null;
        }
        if (!isset($config['port'])) {
            $config['port'] = null;
        }
        if (!isset($config['socket'])) {
            $config['socket'] = null;
        }

        return $config;
    }
}
