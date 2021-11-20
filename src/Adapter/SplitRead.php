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
 * @author     Michael J. Rubinsky <mrubinsk@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Adapter
 */

namespace Horde\Db\Adapter;

use Horde\Db\Adapter;
use Horde\Db\DbException;
use PDOStatement;

/**
 * SplitRead:: class wraps two individual adapters to
 * provide support for split read/write database setups.
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @author     Michael J. Rubinsky <mrubinsk@horde.org>
 * @category   Horde
 * @copyright  2007 Maintainable Software, LLC
 * @copyright  2008-2021 Horde LLC
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Adapter
 */
class SplitRead implements Adapter
{
    /**
     * The read adapter
     *
     * @var Adapter
     */
    private $read;

    /**
     * The write adapter
     *
     * @var Adapter
     */
    private $write;

    private $lastQuery;

    /**
     * Const'r
     *
     * @param Adapter $read
     * @param Adapter $write
     */
    public function __construct(Adapter $read, Adapter $write)
    {
        $this->read = $read;
        $this->write = $write;
    }

    /**
     * Delegate unknown methods to the _write adapter.
     *
     * @param string $method
     * @param array $args
     */
    public function __call($method, $args)
    {
        $result = call_user_func_array(array($this->write, $method), $args);
        $this->lastQuery = $this->write->getLastQuery();
        return $result;
    }

    /**
     * Returns the human-readable name of the adapter.  Use mixed case - one
     * can always use downcase if needed.
     *
     * @return string
     */
    public function adapterName()
    {
        return 'SplitRead';
    }

    /**
     * Does this adapter support migrations?
     *
     * @return bool
     */
    public function supportsMigrations()
    {
        return $this->write->supportsMigrations();
    }

    /**
     * Does this adapter support using DISTINCT within COUNT?  This is +true+
     * for all adapters except sqlite.
     *
     * @return bool
     */
    public function supportsCountDistinct()
    {
        return $this->read->supportsCountDistinct();
    }

    /**
     * Should primary key values be selected from their corresponding
     * sequence before the insert statement?  If true, next_sequence_value
     * is called before each insert to set the record's primary key.
     * This is false for all adapters but Firebird.
     *
     * @return bool
     */
    public function prefetchPrimaryKey($tableName = null)
    {
        return $this->write->prefetchPrimaryKey($tableName);
    }

    /*##########################################################################
    # Connection Management
    ##########################################################################*/

    /**
     * Connect to the db.
     * @TODO: Lazy connect?
     *
     */
    public function connect()
    {
        $this->write->connect();
        $this->read->connect();
    }

    /**
     * Is the connection active?
     *
     * @return bool
     */
    public function isActive()
    {
        return ($this->read->isActive() && $this->write->isActive());
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
        $this->read->disconnect();
        $this->write->disconnect();
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
        return $this->write->rawConnection();
    }


    /*##########################################################################
    # Quoting
    ##########################################################################*/

    /**
     * Quotes a string, escaping any special characters.
     *
     * @param   string  $string
     * @return  string
     */
    public function quoteString($string)
    {
        return $this->read->quoteString($string);
    }


    /*##########################################################################
    # Database Statements
    ##########################################################################*/

    /**
     * Returns an array of records with the column names as keys, and
     * column values as values.
     *
     * @param string  $sql   SQL statement.
     * @param mixed $arg1    Either an array of bound parameters or a query
     *                       name.
     * @param string $arg2   If $arg1 contains bound parameters, the query
     *                       name.
     *
     * @return PDOStatement
     * @throws DbException
     */
    public function select($sql, $arg1 = null, $arg2 = null)
    {
        $result = $this->read->select($sql, $arg1, $arg2);
        $this->lastQuery = $this->read->getLastQuery();
        return $result;
    }

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
        $result = $this->read->selectAll($sql, $arg1, $arg2);
        $this->lastQuery = $this->read->getLastQuery();
        return $result;
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
        $result = $this->read->selectOne($sql, $arg1, $arg2);
        $this->lastQuery = $this->read->getLastQuery();
        return $result;
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
        $result = $this->read->selectValue($sql, $arg1, $arg2);
        $this->lastQuery = $this->read->getLastQuery();
        return $result;
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
        $result = $this->read->selectValues($sql, $arg1, $arg2);
        $this->lastQuery = $this->read->getLastQuery();
        return $result;
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
        $result = $this->read->selectAssoc($sql, $arg1, $arg2);
        $this->lastQuery = $this->read->getLastQuery();
        return $result;
    }

    /**
     * Executes the SQL statement in the context of this connection.
     *
     * @param string $sql   SQL statement.
     * @param mixed $arg1   Either an array of bound parameters or a query
     *                      name.
     * @param string $arg2  If $arg1 contains bound parameters, the query
     *                      name.
     *
     * @return PDOStatement
     * @throws DbException
     */
    public function execute($sql, $arg1 = null, $arg2 = null)
    {
        // Can't assume this will always be a read action, use _write.
        $result = $this->write->execute($sql, $arg1, $arg2);
        $this->lastQuery = $this->write->getLastQuery();

        // Once doing writes, keep using the write backend even for reads
        // at least during the same request, to help against stale data.
        $this->read = $this->write;

        return $result;
    }

    /**
     * Returns the last auto-generated ID from the affected table.
     *
     * @param string $sql           SQL statement.
     * @param mixed $arg1           Either an array of bound parameters or a
     *                              query name.
     * @param string $arg2          If $arg1 contains bound parameters, the
     *                              query name.
     * @param string $pk            TODO
     * @param mixed  $idValue       TODO
     * @param string $sequenceName  TODO
     *
     * @return int  Last inserted ID.
     * @throws DbException
     */
    public function insert(
        $sql,
        $arg1 = null,
        $arg2 = null,
        $pk = null,
        $idValue = null,
        $sequenceName = null
    )
    {
        $result = $this->write->insert($sql, $arg1, $arg2, $pk, $idValue, $sequenceName);
        $this->lastQuery = $this->write->getLastQuery();

        // Once doing writes, keep using the write backend even for reads
        // at least during the same request, to help against stale data.
        $this->read = $this->write;

        return $result;
    }

    /**
     * Inserts a row including BLOBs into a table.
     *
     * @since Horde_Db 2.1.0
     *
     * @param string $table     The table name.
     * @param array $fields     A hash of column names and values. BLOB columns
     *                          must be provided as Horde_Db_Value_Binary
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
        $result = $this->write->insertBlob($table, $fields, $pk, $idValue);
        $this->lastQuery = $this->write->getLastQuery();

        // Once doing writes, keep using the write backend even for reads
        // at least during the same request, to help against stale data.
        $this->read = $this->write;

        return $result;
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
        $result = $this->write->update($sql, $arg1, $arg2);
        $this->lastQuery = $this->write->getLastQuery();

        // Once doing writes, keep using the write backend even for reads
        // at least during the same request, to help against stale data.
        $this->read = $this->write;

        return $result;
    }

    /**
     * Updates rows including BLOBs into a table.
     *
     * @since Horde_Db 2.2.0
     *
     * @param string $table  The table name.
     * @param array $fields  A hash of column names and values. BLOB columns
     *                       must be provided as Horde_Db_Value_Binary objects.
     * @param string $where  A WHERE clause.
     *
     * @throws DbException
     */
    public function updateBlob($table, $fields, $where = '')
    {
        $result = $this->write->updateBlob($table, $fields, $where);
        $this->lastQuery = $this->write->getLastQuery();

        // Once doing writes, keep using the write backend even for reads
        // at least during the same request, to help against stale data.
        $this->read = $this->write;

        return $result;
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
        $result = $this->write->delete($sql, $arg1, $arg2);
        $this->lastQuery = $this->write->getLastQuery();

        // Once doing writes, keep using the write backend even for reads
        // at least during the same request, to help against stale data.
        $this->read = $this->write;

        return $result;
    }

    /**
     * Check if a transaction has been started.
     *
     * @return bool  True if transaction has been started.
     */
    public function transactionStarted()
    {
        $result = $this->write->transactionStarted();
        $this->lastQuery = $this->write->getLastQuery();
        return $result;
    }
    /**
     * Begins the transaction (and turns off auto-committing).
     */
    public function beginDbTransaction()
    {
        $result = $this->write->beginDbTransaction();
        $this->lastQuery = $this->write->getLastQuery();
        return $result;
    }

    /**
     * Commits the transaction (and turns on auto-committing).
     */
    public function commitDbTransaction()
    {
        $result = $this->write->commitDbTransaction();
        $this->lastQuery = $this->write->getLastQuery();
        return $result;
    }

    /**
     * Rolls back the transaction (and turns on auto-committing). Must be
     * done if the transaction block raises an exception or returns false.
     */
    public function rollbackDbTransaction()
    {
        $result = $this->write->rollbackDbTransaction();
        $this->lastQuery = $this->write->getLastQuery();
        return $result;
    }

    /**
     * Appends +LIMIT+ and +OFFSET+ options to a SQL statement.
     *
     * @param string $sql     SQL statement.
     * @param array $options  TODO
     *
     * @return string
     */
    public function addLimitOffset($sql, $options)
    {
        $result = $this->read->addLimitOffset($sql, $options);
        $this->lastQuery = $this->write->getLastQuery();
        return $result;
    }

    /**
     * Appends a locking clause to an SQL statement.
     * This method *modifies* the +sql+ parameter.
     * 
     * TODO: BC Break refactor to return changed string
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
        $this->write->addLock($sql, $options);
        $this->lastQuery = $this->write->getLastQuery();
    }

    public function getLastQuery(): string
    {
        return $this->lastQuery;
    }

    /**
     * Writes values to the cache handler.
     *
     * The key is automatically prefixed to avoid collisions when using
     * different adapters or different configurations.
     *
     * Implementing this for the split adapter makes limited sense but it
     * makes the relation between the Adapter interface and the Base adapter
     * less of a hassle.
     *
     * @since Horde_Db 2.1.0
     *
     * @param string $key    A cache key.
     * @param string $value  A value.
     */
    public function cacheWrite(string $key, string $value)
    {
        // No use in writing a cache for the write adapter
        $this->read->cacheWrite($key, $value);
    }

    /**
     * Reads values from the cache handler.
     *
     * The key is automatically prefixed to avoid collisions when using
     * different adapters or different configurations.
     * 
     * Implementing this for the split adapter makes limited sense but it
     * makes the relation between the Adapter interface and the Base adapter
     * less of a hassle.
     *
     * @since Horde_Db 2.1.0
     *
     * @param string $key  A cache key.
     *
     * @return string|false  A value.
     */
    public function cacheRead($key)
    {
        return $this->read->cacheRead($key);
    }

}
