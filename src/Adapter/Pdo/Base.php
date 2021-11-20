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

namespace Horde\Db\Adapter\Pdo;

use Horde\Db\Adapter\Base as BaseAdapter;
use PDO;
use PDOException;
use PDOStatement;
use Horde\Db\DbException;
use Horde\Db\Value\Binary;
use Horde_Support_Timer;
use Horde\Db\Value;

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
 * @subpackage Adapter
 */
abstract class Base extends BaseAdapter
{
    /*##########################################################################
    # Connection Management
    ##########################################################################*/

    /**
     * Connect to the db
     */
    public function connect()
    {
        if ($this->active) {
            return;
        }

        list($dsn, $user, $pass) = $this->parseConfig();

        try {
            $pdo = @new PDO($dsn, $user, $pass);
        } catch (PDOException $e) {
            $msg = 'Could not instantiate PDO. PDOException: '
                . $e->getMessage();
            $this->logError($msg, '');

            $e2 = new DbException($msg);
            $e2->logged = true;
            throw $e2;
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);

        $this->connection = $pdo;
        $this->active     = true;
    }

    /**
     * Check if the connection is active
     *
     * @return  boolean
     */
    public function isActive()
    {
        $this->lastQuery = $sql = 'SELECT 1';
        try {
            return isset($this->connection) &&
                $this->connection->query($sql);
        } catch (PDOException $e) {
            throw new DbException($e);
        }
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
     * @return Result
     * @throws DbException
     */
    public function select($sql, $arg1 = null, $arg2 = null)
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
        $stmt = $this->execute($sql, $arg1, $arg2);
        if (!$stmt) {
            return [];
        }
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Required to really close the connection.
        $stmt = null;
        return $result;
    }

    /**
     * Returns a record hash with the column names as keys and column values as
     * values.
     *
     * @param string $sql   A query.
     * @param mixed  $arg1  Either an array of bound parameters or a query name.
     * @param string $arg2  If $arg1 contains bound parameters, the query name.
     *
     * @return array|bool  A record hash or false if no record found.
     */
    public function selectOne($sql, $arg1 = null, $arg2 = null)
    {
        $stmt = $this->execute($sql, $arg1, $arg2);
        if (!$stmt) {
            return [];
        }
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        // Required to really close the connection.
        $stmt = null;
        return $result;
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
        $stmt = $this->execute($sql, $arg1, $arg2);
        if (!$stmt) {
            return null;
        }
        $result = $stmt->fetchColumn(0);
        // Required to really close the connection.
        $stmt = null;
        return $result;
    }

    /**
     * Returns an array of the values of the first column in a select:
     *   selectValues("SELECT id FROM companies LIMIT 3") => [1,2,3]
     *
     * @param   string  $sql
     * @param   mixed   $arg1  Either an array of bound parameters or a query name.
     * @param   string  $arg2  If $arg1 contains bound parameters, the query name.
     */
    public function selectValues($sql, $arg1=null, $arg2=null)
    {
        $stmt = $this->execute($sql, $arg1, $arg2);
        if (!$stmt) {
            return null;
        }
        $result = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        // Required to really close the connection.
        $stmt = null;
        return $result;
    }

    /**
     * Returns an array where the keys are the first column of a select, and the
     * values are the second column:
     *
     *   selectAssoc("SELECT id, name FROM companies LIMIT 3") => [1 => 'Ford', 2 => 'GM', 3 => 'Chrysler']
     *
     * @param   string  $sql
     * @param   mixed   $arg1  Either an array of bound parameters or a query name.
     * @param   string  $arg2  If $arg1 contains bound parameters, the query name.
     */
    public function selectAssoc($sql, $arg1=null, $arg2=null)
    {
        $stmt = $this->execute($sql, $arg1, $arg2);
        if (!$stmt) {
            return null;
        }
        $result = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        // Required to really close the connection.
        $stmt = null;
        return $result;
    }

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
     * @return PDOStatement
     * @throws DbException
     */
    public function execute($sql, $arg1 = null, $arg2 = null)
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

        try {
            $this->lastQuery = $query;
            $stmt = $this->connection->query($query);
        } catch (PDOException $e) {
            $this->logInfo($sql, $arg1, $name);
            $this->logError($query, 'QUERY FAILED: ' . $e->getMessage());
            throw new DbException($e);
        }

        $this->logInfo($sql, $arg1, $name, $t->pop());
        $this->rowCount = $stmt ? $stmt->rowCount() : 0;

        return $stmt;
    }

    /**
     * Use a PDO prepared statement to execute a query. Used when passing
     * values to insert/update as a stream resource.
     *
     * @param  string $sql           The SQL statement. Includes '?' placeholder
     *     for binding non-stream values. Stream values are bound using a
     *     placeholders named like ':binary0', ':binary1' etc...
     *
     * @param  array $values        An array of non-stream values.
     * @param  array $binary_values An array of stream resources.
     *
     * @throws  DbException
     */
    protected function executePrepared($sql, $values, $binary_values)
    {
        $query = $this->replaceParameters($sql, $values);
        try {
            $stmt = $this->connection->prepare($query);
            foreach ($binary_values as $key => $bvalue) {
                rewind($bvalue);
                $stmt->bindParam(':binary' . $key, $bvalue, PDO::PARAM_LOB);
            }
        } catch (PDOException $e) {
            $this->logInfo($sql, $values, null);
            $this->logError($sql, 'QUERY FAILED: ' . $e->getMessage());
            throw new DbException($e);
        }

        $t = new Horde_Support_Timer();
        $t->push();

        try {
            $this->lastQuery = $sql;
            $stmt->execute();
        } catch (PDOException $e) {
            $this->logInfo($sql, $values, null);
            $this->logError($sql, 'QUERY FAILED: ' . $e->getMessage());
            throw new DbException($e);
        }

        $t = new Horde_Support_Timer();
        $t->push();

        $this->logInfo($sql, $values, null, $t->pop());
        $this->rowCount = $stmt->rowCount();
    }

    /**
     * Inserts a row including BLOBs into a table.
     *
     * @since Horde_Db 2.4.0
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
        $placeholders = $values = $binary = [];
        $binary_cnt = 0;
        foreach ($fields as $name => $value) {
            if ($value instanceof Binary) {
                $placeholders[] = ':binary' . $binary_cnt++;
                $binary[] = $value->stream;
            } else {
                $placeholders[] = '?';
                $values[] = $value;
            }
        }

        $query = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->quoteTableName($table),
            implode(', ', array_map(array($this, 'quoteColumnName'), array_keys($fields))),
            implode(', ', $placeholders)
        );

        if ($binary_cnt > 0) {
            $this->executePrepared($query, $values, $binary);

            try {
                return $idValue
                    ? $idValue
                    : $this->connection->lastInsertId(null);
            } catch (PDOException $e) {
                throw new DbException($e);
            }
        }

        return $this->insert($query, $fields, null, $pk, $idValue);
    }

    /**
     * Updates rows including BLOBs into a table.
     *
     * @since Horde_Db 2.4.0
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

        $values = $binary_values = $fnames = [];
        $binary_cnt = 0;

        foreach ($fields as $field => $value) {
            if ($value instanceof Value) {
                $fnames[] = $this->quoteColumnName($field) . ' = :binary' . $binary_cnt++;
                $binary_values[] = $value->stream;
            } else {
                $fnames[] = $this->quoteColumnName($field) . ' = ?';
                $values[] = $value;
            }
        }

        $query = sprintf(
            'UPDATE %s SET %s%s',
            $this->quoteTableName($table),
            implode(', ', $fnames),
            strlen($where) ? ' WHERE ' . $where : ''
        );

        if ($binary_cnt > 0) {
            $this->executePrepared($query, $values, $binary_values);
            return $this->rowCount;
        }

        return $this->update($query, $fields);
    }


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
    ) {
        $this->execute($sql, $arg1, $arg2);

        try {
            return $idValue
                ? $idValue
                : $this->connection->lastInsertId($sequenceName);
        } catch (PDOException $e) {
            throw new DbException($e);
        }
    }

    /**
     * Begins the transaction (and turns off auto-committing).
     */
    public function beginDbTransaction()
    {
        if (!$this->transactionStarted) {
            try {
                $this->connection->beginTransaction();
            } catch (PDOException $e) {
                throw new DbException($e);
            }
        }
        $this->transactionStarted++;
    }

    /**
     * Commits the transaction (and turns on auto-committing).
     */
    public function commitDbTransaction()
    {
        $this->transactionStarted--;
        if (!$this->transactionStarted) {
            try {
                $this->connection->commit();
            } catch (PDOException $e) {
                throw new DbException($e);
            }
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

        try {
            $this->connection->rollBack();
        } catch (PDOException $e) {
            throw new DbException($e);
        }
        $this->transactionStarted = 0;
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
        try {
            return $this->connection->quote($string);
        } catch (PDOException $e) {
            throw new DbException($e);
        }
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    protected function normalizeConfig($params)
    {
        // Normalize config parameters to what PDO expects.
        $normalize = array('database' => 'dbname',
                           'hostspec' => 'host');

        foreach ($normalize as $from => $to) {
            if (isset($params[$from])) {
                $params[$to] = $params[$from];
                unset($params[$from]);
            }
        }

        return $params;
    }

    protected function buildDsnString($params)
    {
        $dsn = $this->config['adapter'] . ':';
        foreach ($params as $k => $v) {
            if (strlen($v)) {
                $dsn .= "$k=$v;";
            }
        }
        return rtrim($dsn, ';');
    }

    /**
     * Parse configuration array into options for PDO constructor.
     *
     * @throws  DbException
     * @return  array  [dsn, username, password]
     */
    protected function parseConfig()
    {
        $this->checkRequiredConfig(array('adapter', 'username'));

        // try an empty password if it's not set.
        if (!isset($this->config['password'])) {
            $this->config['password'] = '';
        }

        // collect options to build PDO Data Source Name (DSN) string
        $dsnOpts = $this->config;
        unset(
            $dsnOpts['adapter'],
            $dsnOpts['username'],
            $dsnOpts['password'],
            $dsnOpts['protocol'],
            $dsnOpts['persistent'],
            $dsnOpts['charset'],
            $dsnOpts['phptype'],
            $dsnOpts['socket']
        );

        // return DSN and user/pass for connection
        return array(
            $this->buildDsnString($this->normalizeConfig($dsnOpts)),
            $this->config['username'],
            $this->config['password']);
    }
}
