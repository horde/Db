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

namespace Horde\Db;

use Horde\Db\Adapter\Base\Result;

/**
 * For compatibility reasons, we need to extend the Horde_Db_Adapter interface.
 *
 * Otherwise, migrations based on Horde_Db_Migration_Base and migrations
 * based on Horde\Db\Migration\Base could not coexist.
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
interface Adapter extends \Horde_Db_Adapter
{
    /**
     * Returns the human-readable name of the adapter.  Use mixed case - one
     * can always use downcase if needed.
     *
     * @return string
     */
    public function adapterName();

    /**
     * Does this adapter support migrations?  Backend specific, as the
     * abstract adapter always returns +false+.
     *
     * @return bool
     */
    public function supportsMigrations();

    /**
     * Does this adapter support using DISTINCT within COUNT?  This is +true+
     * for all adapters except sqlite.
     *
     * @return bool
     */
    public function supportsCountDistinct();

    /**
     * Should primary key values be selected from their corresponding
     * sequence before the insert statement?  If true, next_sequence_value
     * is called before each insert to set the record's primary key.
     * This is false for all adapters but Firebird.
     *
     * @return bool
     */
    public function prefetchPrimaryKey($tableName = null);


    /*##########################################################################
    # Connection Management
    ##########################################################################*/

    /**
     * Connect to the db.
     */
    public function connect();

    /**
     * Is the connection active?
     *
     * @return bool
     */
    public function isActive();

    /**
     * Reconnect to the db.
     */
    public function reconnect();

    /**
     * Disconnect from db.
     */
    public function disconnect();

    /**
     * Provides access to the underlying database connection. Useful for when
     * you need to call a proprietary method such as postgresql's
     * lo_* methods.
     *
     * @return resource
     */
    public function rawConnection();


    /*##########################################################################
    # Quoting
    ##########################################################################*/

    /**
     * Quotes a string, escaping any special characters.
     *
     * @param   string  $string
     * @return  string
     */
    public function quoteString($string);


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
     * @return Result
     * @throws DbException
     */
    public function select($sql, $arg1 = null, $arg2 = null);

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
    public function selectAll($sql, $arg1 = null, $arg2 = null);

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
    public function selectOne($sql, $arg1 = null, $arg2 = null);

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
    public function selectValue($sql, $arg1 = null, $arg2 = null);

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
    public function selectValues($sql, $arg1 = null, $arg2 = null);

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
    public function selectAssoc($sql, $arg1 = null, $arg2 = null);

    /**
     * Executes the SQL statement in the context of this connection.
     *
     * @internal  Deprecated for external usage. Use select() instead.
     *
     * @param string $sql   SQL statement.
     * @param mixed $arg1   Either an array of bound parameters or a query
     *                      name.
     * @param string $arg2  If $arg1 contains bound parameters, the query
     *                      name.
     *
     * @return mixed
     * @throws DbException
     */
    public function execute($sql, $arg1 = null, $arg2 = null);

    /**
     * Inserts a row into a table.
     *
     * @param string $sql           SQL statement.
     * @param array|string $arg1    Either an array of bound parameters or a
     *                              query name.
     * @param string $arg2          If $arg1 contains bound parameters, the
     *                              query name.
     * @param string $pk            The primary key column.
     * @param mixed  $idValue       The primary key value. This parameter is
     *                              required if the primary key is inserted
     *                              manually.
     * @param string $sequenceName  The sequence name.
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
    );

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
    public function insertBlob($table, $fields, $pk = null, $idValue = null);

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
    public function update($sql, $arg1 = null, $arg2 = null);

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
    public function updateBlob($table, $fields, $where = '');

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
    public function delete($sql, $arg1 = null, $arg2 = null);

    /**
     * Check if a transaction has been started.
     *
     * @return bool  True if transaction has been started.
     */
    public function transactionStarted();

    /**
     * Begins the transaction (and turns off auto-committing).
     */
    public function beginDbTransaction();

    /**
     * Commits the transaction (and turns on auto-committing).
     */
    public function commitDbTransaction();

    /**
     * Rolls back the transaction (and turns on auto-committing). Must be
     * done if the transaction block raises an exception or returns false.
     */
    public function rollbackDbTransaction();

    /**
     * Appends +LIMIT+ and +OFFSET+ options to a SQL statement.
     *
     * @param string $sql     SQL statement.
     * @param array $options  TODO
     *
     * @return string
     */
    public function addLimitOffset($sql, $options);

    /**
     * Appends a locking clause to an SQL statement.
     * This method *modifies* the +sql+ parameter.
     *
     *   # SELECT * FROM suppliers FOR UPDATE
     *   add_lock! 'SELECT * FROM suppliers', :lock => true
     *   add_lock! 'SELECT * FROM suppliers', :lock => ' FOR UPDATE'
     *
     * @param string &$sql    SQL statment.
     * @param array $options  TODO.
     */
    public function addLock(&$sql, array $options = []);

    public function getLastQuery(): string;

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
    public function cacheWrite(string $key, string $value);

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
    public function cacheRead($key);
}