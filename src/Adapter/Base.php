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

namespace Horde\Db\Adapter;

use Horde\Db\Adapter;
use Horde\Db\DbException;
use Horde_Cache;
use Horde_Log_Logger;
use Horde\Db\Adapter\Base\Schema;
use Horde_Support_Stub;
use BadMethodCallException;
use Horde_Support_Backtrace;
use Horde\Db\Value\Binary;

/**
 *
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
 * @method string quoteTableName(string $name)
 * @method string quoteColumnName(string $name)
 * @method quote($value, $column = null)

 */
abstract class Base implements Adapter
{
    /**
     * Config options.
     *
     * @var array
     */
    protected $config = [];

    /**
     * DB connection.
     *
     * @var mixed
     */
    protected $connection = null;

    /**
     * Has a transaction been started?
     *
     * @var int
     */
    protected $transactionStarted = 0;

    /**
     * The last query sent to the database.
     *
     * @var string
     */
    protected $lastQuery;

    /**
     * Row count of last action.
     *
     * @var int
     */
    protected $rowCount = null;

    /**
     * Runtime of last query.
     *
     * @var int
     */
    protected $runtime;

    /**
     * Is connection active?
     *
     * @var bool
     */
    protected $active = null;

    /**
     * Cache object.
     *
     * @var Horde_Cache
     */
    protected $cache;

    /**
     * Cache prefix.
     *
     * @var string
     */
    protected $cachePrefix;

    /**
     * Log object.
     *
     * @var Horde_Log_Logger
     */
    protected $logger;

    /**
     * Schema object.
     *
     * @var Schema
     */
    protected $schema = null;

    /**
     * Schema class to use.
     *
     * @var string
     */
    protected $schemaClass = null;

    /**
     * List of schema methods.
     *
     * @var array
     */
    protected $schemaMethods = [];

    /**
     * Log query flag
     *
     * @var bool
     */
    protected $logQueries = false;


    /*##########################################################################
    # Construct/Destruct
    ##########################################################################*/

    /**
     * Constructor.
     *
     * @param array $config  Configuration options and optional objects:
     * <pre>
     * 'charset' - (string) TODO
     * </pre>
     */
    public function __construct($config)
    {
        /* Can't set cache/logger in constructor - these objects may use DB
         * for storage. Add stubs for now - they have to be manually set
         * later with setCache() and setLogger(). */
        $this->cache = new Horde_Support_Stub();
        $this->logger = new Horde_Support_Stub();

        // Default to UTF-8
        if (!isset($config['charset'])) {
            $config['charset'] = 'UTF-8';
        }

        $this->config  = $config;
        $this->runtime = 0;

        // TODO: This is not really namespace-ready
        if (!$this->schemaClass) {
            $this->schemaClass = __CLASS__ . '_Schema';
        }

        $this->connect();
    }

    /**
     * Free any resources that are open.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Serialize callback.
     */
    public function __sleep()
    {
        return array_diff(array_keys(get_class_vars(__CLASS__)), array('active', 'connection'));
    }

    /**
     * Unserialize callback.
     */
    public function __wakeup()
    {
        $this->schema->setAdapter($this);
        $this->connect();
    }

    /**
     * Returns an adaptor option set through the constructor.
     *
     * @param string $option  The option to return.
     *
     * @return mixed  The option value or null if option doesn't exist or is
     *                not set.
     */
    public function getOption($option)
    {
        return isset($this->config[$option]) ? $this->config[$option] : null;
    }

    /*##########################################################################
    # Dependency setters/getters
    ##########################################################################*/

    /**
     * Set a cache object.
     *
     * @inject
     *
     * @param Horde_Cache $cache  The cache object.
     */
    public function setCache(Horde_Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @return Horde_Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Set a logger object.
     *
     * @inject
     *
     * @var Horde_Log_Logger $logger  The logger object.
     * @var  boolean $log_queries     If true, logs all queries at DEBUG level.
     *       NOTE: Horde_Db_Value_Binary objects and stream resource values are
     *             NEVER included in the log output regardless of this setting.
     *             @since  2.4.0
     */
    public function setLogger(Horde_Log_Logger $logger, $log_queries = false)
    {
        $this->logger = $logger;
        $this->logQueries = $log_queries;
    }

    /**
     * return Horde_Log_Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }


    /*##########################################################################
    # Object composition
    ##########################################################################*/

    /**
     * Delegate calls to the schema object.
     *
     * @param  string  $method
     * @param  array   $args
     *
     * @return mixed  TODO
     * @throws BadMethodCallException
     */
    public function __call($method, $args)
    {
        if (!$this->schema) {
            // Create the database-specific (but not adapter specific) schema
            // object.
            $this->schema = new $this->schemaClass($this, array(
                'cache' => $this->cache,
                'logger' => $this->logger
            ));
            $this->schemaMethods = array_flip(get_class_methods($this->schema));
        }

        if (isset($this->schemaMethods[$method])) {
            return call_user_func_array(array($this->schema, $method), $args);
        }

        $support = new Horde_Support_Backtrace();
        $context = $support->getContext(1);
        $caller = $context['function'];
        if (isset($context['class'])) {
            $caller = $context['class'] . '::' . $caller;
        }
        throw new BadMethodCallException('Call to undeclared method "' . get_class($this) . '::' . $method . '" from "' . $caller . '"');
    }


    /*##########################################################################
    # Public
    ##########################################################################*/

    /**
     * Returns the human-readable name of the adapter.  Use mixed case - one
     * can always use downcase if needed.
     *
     * @return string
     */
    public function adapterName()
    {
        return 'Base';
    }

    /**
     * Does this adapter support migrations?  Backend specific, as the
     * abstract adapter always returns +false+.
     *
     * @return bool
     */
    public function supportsMigrations()
    {
        return false;
    }

    /**
     * Does this adapter support using DISTINCT within COUNT?  This is +true+
     * for all adapters except sqlite.
     *
     * @return bool
     */
    public function supportsCountDistinct()
    {
        return true;
    }

    /**
     * Does this adapter support using INTERVAL statements?  This is +true+
     * for all adapters except sqlite.
     *
     * @return bool
     */
    public function supportsInterval()
    {
        return true;
    }

    /**
     * Should primary key values be selected from their corresponding
     * sequence before the insert statement?  If true, next_sequence_value
     * is called before each insert to set the record's primary key.
     * This is false for all adapters but Firebird.
     *
     * @deprecated
     * @return bool
     */
    public function prefetchPrimaryKey($tableName = null)
    {
        return false;
    }

    /**
     * Get the last query run
     *
     * @return string
     */
    public function getLastQuery(): string
    {
        return $this->lastQuery;
    }

    /**
     * Reset the timer
     *
     * @return int
     */
    public function resetRuntime()
    {
        $this->runtime = 0;

        return $this->runtime;
    }

    /**
     * Writes values to the cache handler.
     *
     * The key is automatically prefixed to avoid collisions when using
     * different adapters or different configurations.
     *
     * @since Horde_Db 2.1.0
     *
     * @param string $key    A cache key.
     * @param string $value  A value.
     */
    public function cacheWrite($key, $value)
    {
        $this->cache->set($this->cacheKey($key), $value);
    }

    /**
     * Reads values from the cache handler.
     *
     * The key is automatically prefixed to avoid collisions when using
     * different adapters or different configurations.
     *
     * @since Horde_Db 2.1.0
     *
     * @param string $key  A cache key.
     *
     * @return string|false  A value.
     */
    public function cacheRead($key)
    {
        return $this->cache->get($this->cacheKey($key), 0);
    }


    
    /*##########################################################################
    # Connection Management
    ##########################################################################*/

    /**
     * Is the connection active?
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->active && $this->connection;
    }

    /**
     * Reconnect to the db.
     */
    public function reconnect()
    {
        $this->disconnect();
        $this->connect();
    }

    /**
     * Disconnect from db.
     */
    public function disconnect()
    {
        $this->connection = null;
        $this->active = false;
    }

    /**
     * Provides access to the underlying database connection. Useful for when
     * you need to call a proprietary method such as postgresql's
     * lo_* methods.
     *
     * @return resource
     */
    public function rawConnection()
    {
        return $this->connection;
    }


    /*##########################################################################
    # Database Statements
    ##########################################################################*/

    /**
     * Returns an array of record hashes with the column names as keys and
     * column values as values.
     *
     * @param string $sql   SQL statement.
     * @param mixed $arg1   Either an array of bound parameters or a query
     *                      name.
     * @param string $arg2  If $arg1 contains bound parameters, the query
     *                      name.
     *
     * @return array
     * @throws DbException
     */
    public function selectAll($sql, $arg1 = null, $arg2 = null)
    {
        $rows = [];
        $result = $this->select($sql, $arg1, $arg2);
        if ($result) {
            foreach ($result as $row) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /**
     * Returns a record hash with the column names as keys and column values
     * as values.
     *
     * @param string $sql   SQL statement.
     * @param mixed $arg1   Either an array of bound parameters or a query
     *                      name.
     * @param string $arg2  If $arg1 contains bound parameters, the query
     *                      name.
     *
     * @return array
     * @throws DbException
     */
    public function selectOne($sql, $arg1 = null, $arg2 = null)
    {
        $result = $this->selectAll($sql, $arg1, $arg2);
        return $result
            ? next($result)
            : [];
    }

    /**
     * Returns a single value from a record
     *
     * @param string $sql   SQL statement.
     * @param mixed $arg1   Either an array of bound parameters or a query
     *                      name.
     * @param string $arg2  If $arg1 contains bound parameters, the query
     *                      name.
     *
     * @return string
     * @throws DbException
     */
    public function selectValue($sql, $arg1 = null, $arg2 = null)
    {
        $result = $this->selectOne($sql, $arg1, $arg2);

        return $result
            ? next($result)
            : null;
    }

    /**
     * Returns an array of the values of the first column in a select:
     *   selectValues("SELECT id FROM companies LIMIT 3") => [1,2,3]
     *
     * @param string $sql   SQL statement.
     * @param mixed $arg1   Either an array of bound parameters or a query
     *                      name.
     * @param string $arg2  If $arg1 contains bound parameters, the query
     *                      name.
     *
     * @return array
     * @throws DbException
     */
    public function selectValues($sql, $arg1 = null, $arg2 = null)
    {
        $result = $this->selectAll($sql, $arg1, $arg2);
        $values = [];
        foreach ($result as $row) {
            $values[] = next($row);
        }
        return $values;
    }

    /**
     * Returns an array where the keys are the first column of a select, and the
     * values are the second column:
     *
     *   selectAssoc("SELECT id, name FROM companies LIMIT 3") => [1 => 'Ford', 2 => 'GM', 3 => 'Chrysler']
     *
     * @param string $sql   SQL statement.
     * @param mixed $arg1   Either an array of bound parameters or a query
     *                      name.
     * @param string $arg2  If $arg1 contains bound parameters, the query
     *                      name.
     *
     * @return array
     * @throws DbException
     */
    public function selectAssoc($sql, $arg1 = null, $arg2 = null)
    {
        $result = $this->selectAll($sql, $arg1, $arg2);
        $values = [];
        foreach ($result as $row) {
            $values[current($row)] = next($row);
        }
        return $values;
    }

    /**
     * Inserts a row including BLOBs into a table.
     *
     * @since Horde_Db 2.1.0
     *
     * @param string $table     The table name.
     * @param array $fields     A hash of column names and values. BLOB/CLOB
     *                          columns must be provided as Horde_Db_Value
     *                          objects.
     * @param string $pk        The primary key column.
     * @param mixed  $idValue   The primary key value. This parameter is
     *                          required if the primary key is inserted
     *                          manually.
     *
     * @return int  Last inserted ID.
     * @throws DbException
     */
    public function insertBlob($table, $fields, $pk = null, $idValue = null)
    {
        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteTableName($table),
            implode(', ', array_map(array($this, 'quoteColumnName'), array_keys($fields))),
            implode(', ', array_fill(0, count($fields), '?'))
        );
        return $this->insert($query, $fields, null, $pk, $idValue);
    }

    /**
     * Executes the update statement and returns the number of rows affected.
     *
     * @param string $sql   SQL statement.
     * @param mixed $arg1   Either an array of bound parameters or a query
     *                      name.
     * @param string $arg2  If $arg1 contains bound parameters, the query
     *                      name.
     *
     * @return int  Number of rows affected.
     * @throws DbException
     */
    public function update($sql, $arg1 = null, $arg2 = null)
    {
        $this->execute($sql, $arg1, $arg2);
        return $this->rowCount;
    }

    /**
     * Updates rows including BLOBs into a table.
     *
     * @since Horde_Db 2.2.0
     *
     * @param string $table        The table name.
     * @param array $fields        A hash of column names and values. BLOB/CLOB
     *                             columns must be provided as
     *                             Horde_Db_Value objects.
     * @param string|array $where  A WHERE clause. Either a complete clause or
     *                             an array containing a clause with
     *                             placeholders and a list of values.
     *
     * @throws DbException
     */
    public function updateBlob($table, $fields, $where = null)
    {
        if (is_array($where)) {
            $where = $this->replaceParameters($where[0], $where[1]);
        }
        $fnames = [];
        foreach (array_keys($fields) as $field) {
            $fnames[] = $this->quoteColumnName($field) . ' = ?';
        }
        $query = sprintf(
            'UPDATE %s SET %s%s',
            $this->quoteTableName($table),
            implode(', ', $fnames),
            strlen($where) ? ' WHERE ' . $where : ''
        );
        return $this->update($query, $fields);
    }

    /**
     * Executes the delete statement and returns the number of rows affected.
     *
     * @param string $sql   SQL statement.
     * @param mixed $arg1   Either an array of bound parameters or a query
     *                      name.
     * @param string $arg2  If $arg1 contains bound parameters, the query
     *                      name.
     *
     * @return int  Number of rows affected.
     * @throws DbException
     */
    public function delete($sql, $arg1 = null, $arg2 = null)
    {
        $this->execute($sql, $arg1, $arg2);
        return $this->rowCount;
    }

    /**
     * Check if a transaction has been started.
     *
     * @return bool  True if transaction has been started.
     */
    public function transactionStarted()
    {
        return (bool)$this->transactionStarted;
    }

    /**
     * Appends LIMIT and OFFSET options to a SQL statement.
     *
     * @param string $sql     SQL statement.
     * @param array $options  Hash with 'limit' and (optional) 'offset' values.
     *
     * @return string
     */
    public function addLimitOffset($sql, $options)
    {
        if (isset($options['limit']) && $limit = $options['limit']) {
            if (isset($options['offset']) && $offset = $options['offset']) {
                $sql .= " LIMIT $offset, $limit";
            } else {
                $sql .= " LIMIT $limit";
            }
        }
        return $sql;
    }

    /**
     * TODO
     */
    public function sanitizeLimit($limit)
    {
        return (strpos($limit, ',') !== false)
            ? implode(',', array_map('intval', explode(',', $limit)))
            : intval($limit);
    }

    /**
     * Appends a locking clause to an SQL statement.
     * This method *modifies* the +sql+ parameter.
     * 
     * TODO: BC BREAK Rather return the modified string
     *
     *   # SELECT * FROM suppliers FOR UPDATE
     *   add_lock! 'SELECT * FROM suppliers', :lock => true
     *   add_lock! 'SELECT * FROM suppliers', :lock => ' FOR UPDATE'
     *
     * @param string $sql    SQL statment.
     * @param array $options  TODO.
     */
    public function addLock(&$sql, array $options = [])
    {
        $sql .= (isset($options['lock']) && is_string($options['lock']))
            ? ' ' . $options['lock']
            : ' FOR UPDATE';
    }

    /**
     * Inserts the given fixture into the table. Overridden in adapters that
     * require something beyond a simple insert (eg. Oracle).
     *
     * @param mixed $fixture    TODO
     * @param string $tableName  TODO
     *
     * @return mixed
     */
    public function insertFixture($fixture, $tableName)
    {
        /*@TODO*/
        return $this->execute("INSERT INTO #{quote_table_name(table_name)} (#{fixture.key_list}) VALUES (#{fixture.value_list})", 'Fixture Insert');
    }

    /**
     * TODO
     *
     * @param string $tableName  TODO
     *
     * @return string  TODO
     */
    public function emptyInsertStatement($tableName)
    {
        return 'INSERT INTO ' . $this->quoteTableName($tableName) . ' VALUES(DEFAULT)';
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    /**
     * Checks if required configuration keys are present.
     *
     * @param array $required  Required configuration keys.
     *
     * @throws DbException if a required key is missing.
     */
    protected function checkRequiredConfig(array $required)
    {
        $diff = array_diff_key(array_flip($required), $this->config);
        if (!empty($diff)) {
            $msg = 'Required config missing: ' . implode(', ', array_keys($diff));
            throw new DbException($msg);
        }
    }

    /**
     * Replace ? in a SQL statement with quoted values from $args
     *
     * @param string $sql         SQL statement.
     * @param array $args         An array of values to bind.
     * @param bool $no_binary  If true, do not replace any
     *                            Horde_Db_Value_Binary values. Used for
     *                            logging purposes.
     *
     * @return string  Modified SQL statement.
     * @throws DbException
     */
    protected function replaceParameters($sql, array $args, $no_binary = false)
    {
        $paramCount = substr_count($sql, '?');
        if (count($args) != $paramCount) {
            $this->logError('Parameter count mismatch: ' . $sql, 'Horde_Db_Adapter_Base::_replaceParameters');
            throw new DbException(sprintf('Parameter count mismatch, expecting %d, got %d', $paramCount, count($args)));
        }

        $sqlPieces = explode('?', $sql);
        $sql = array_shift($sqlPieces);
        while (count($sqlPieces)) {
            $value = array_shift($args);
            if ($no_binary && $value instanceof Binary) {
                $sql_value = '<binary_data>';
            } else {
                $sql_value = $this->quote($value);
            }
            $sql .= $sql_value . array_shift($sqlPieces);
        }

        return $sql;
    }

    /**
     * Logs the SQL query for debugging.
     *
     * @param string $sql     SQL statement.
     * @param array  $values  An array of values to substitute for placeholders.
     * @param string $name    Optional queryname.
     * @param float $runtime  Runtime interval.
     */
    protected function logInfo($sql, $values, $name = null, $runtime = null)
    {
        if (!$this->logger || !$this->logQueries) {
            return;
        }

        if (is_array($values)) {
            $sql = $this->replaceParameters($sql, $values, true);
        }

        $name = (empty($name) ? '' : $name)
            . (empty($runtime) ? '' : sprintf(" (%.4fs)", $runtime));

        $this->logger->debug($this->formatLogEntry($name, $sql));
    }

    protected function logError($error, $name, $runtime = null)
    {
        if ($this->logger) {
            $name = (empty($name) ? '' : $name)
                . (empty($runtime) ? '' : sprintf(" (%.4fs)", $runtime));
            $this->logger->err($this->formatLogEntry($name, $error));
        }
    }

    /**
     * Formats the log entry.
     *
     * @param string $message  Message.
     * @param string $sql      SQL statment.
     *
     * @return string  Formatted log entry.
     */
    protected function formatLogEntry($message, $sql)
    {
        return "SQL $message  \n\t" . wordwrap(preg_replace("/\s+/", ' ', $sql), 70, "\n\t  ", 1);
    }

    /**
     * Returns the prefixed cache key to use.
     *
     * @param string $key  A cache key.
     *
     * @return string  Prefixed cache key.
     */
    protected function cacheKey($key)
    {
        if (!isset($this->cachePrefix)) {
            $this->cachePrefix = get_class($this) . hash((version_compare(PHP_VERSION, '5.4', '>=')) ? 'fnv132' : 'sha1', serialize($this->config));
        }

        return $this->cachePrefix . $key;
    }
}
