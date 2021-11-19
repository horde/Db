<?php
/**
 * Copyright 2013-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Adapter
 */
namespace Horde\Db\Adapter;
use \Horde\Db\Adapter;
use \Horde\Db\Adapter\Oracle\Schema;
use \Horde\Db\DbException;
use \Horde\Db\Adapter\Oracle\Result;
use \Horde\Db\Value;
use \Horde\Db\Value\Text;
use \Horde\Db\Value\Binary;
use \Horde_Support_Timer;
use \Horde_String;
/**
 *
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @copyright  2013-2021 Horde LLC
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @since      Horde_Db 2.1.0
 * @subpackage Adapter
 */
class Oci8 extends Base
{
    /**
     * Schema class to use.
     *
     * @var string
     */
    protected $_schemaClass = Schema::class;


    /*#########################################################################
    # Public
    #########################################################################*/

    /**
     * Returns the human-readable name of the adapter.  Use mixed case - one
     * can always use downcase if needed.
     *
     * @return string
     */
    public function adapterName()
    {
        return 'Oracle';
    }

    /**
     * Does this adapter support migrations?
     *
     * @return boolean
     */
    public function supportsMigrations()
    {
        return true;
    }


    /*#########################################################################
    # Connection Management
    #########################################################################*/

    /**
     * Connect to the db
     */
    public function connect()
    {
        if ($this->_active) {
            return;
        }

        $this->_checkRequiredConfig(array('username'));

        if (!isset($this->_config['tns']) && empty($this->_config['host'])) {
            throw new DbException('Either a TNS name or a host name must be specified');
        }

        if (isset($this->_config['tns'])) {
            $connection = $this->_config['tns'];
        } else {
            $connection = $this->_config['host'];
            if (!empty($this->_config['port'])) {
                $connection .= ':' . $this->_config['port'];
            }
            if (!empty($this->_config['service'])) {
                $connection .= '/' . $this->_config['service'];
            }
            if (!empty($this->_config['type'])) {
                $connection .= ':' . $this->_config['type'];
            }
            if (!empty($this->_config['instance'])) {
                $connection .= '/' . $this->_config['instance'];
            }
        }
        $oci = oci_connect(
            $this->_config['username'],
            isset($this->_config['password']) ? $this->_config['password'] : '',
            $connection,
            $this->_oracleCharsetName($this->_config['charset'])
        );
        if (!$oci) {
            if ($error = oci_error()) {
                throw new DbException(
                    sprintf(
                        'Connect failed: (%d) %s',
                        $error['code'],
                        $error['message']
                    ),
                    $error['code']
                );
            } else {
                throw new DbException('Connect failed');
            }
        }

        $this->_connection = $oci;
        $this->_active     = true;

        $this->execute("ALTER SESSION SET NLS_DATE_FORMAT = 'YYYY-MM-DD HH24:MI:SS'");
    }

    /**
     * Disconnect from db
     */
    public function disconnect()
    {
        if ($this->_connection) {
            oci_close($this->_connection);
        }
        parent::disconnect();
    }


    /*#########################################################################
    # Quoting
    #########################################################################*/

    /**
     * Quotes a string, escaping any special characters.
     *
     * @param   string  $string
     * @return  string
     */
    public function quoteString($string)
    {
        return "'" . str_replace("'", "''", $string) . "'";
    }


    /*#########################################################################
    # Database Statements
    #########################################################################*/

    /**
     * Returns an array of records with the column names as keys, and
     * column values as values.
     *
     * @param string $sql    SQL statement.
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
        $stmt = $this->execute($sql, $arg1, $arg2);
        $result = oci_fetch_all($stmt, $rows, 0, -1, OCI_FETCHSTATEMENT_BY_ROW);
        if ($result === false) {
            $this->_handleError($stmt, 'selectAll');
        }
        foreach ($rows as &$row) {
            $row = array_change_key_case($row, CASE_LOWER);
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
        if ($row = oci_fetch_assoc($this->execute($sql, $arg1, $arg2))) {
            return array_change_key_case(
                $row,
                CASE_LOWER
            );
        }
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
        $stmt = $this->execute($sql, $arg1, $arg2);
        if (!oci_fetch($stmt)) {
            return;
        }
        if (($result = oci_result($stmt, 1)) === false) {
            $this->_handleError($stmt, 'selectValue');
        }
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
        $stmt = $this->execute($sql, $arg1, $arg2);
        $values = [];
        while (oci_fetch($stmt)) {
            if (($result = oci_result($stmt, 1)) === false) {
                $this->_handleError($stmt, 'selectValues');
            }
            $values[] = $result;
        }
        return $values;
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
     * @return resource
     * @throws DbException
     */
    public function execute($sql, $arg1 = null, $arg2 = null, $lobs = [])
    {
        if (is_array($arg1)) {
            $query = $this->_replaceParameters($sql, $arg1);
            $name = $arg2;
        } else {
            $name = $arg1;
            $query = $sql;
            $arg1 = [];
        }

        $t = new Horde_Support_Timer;
        $t->push();

        $this->_lastQuery = $query;
        $stmt = @oci_parse($this->_connection, $query);

        $descriptors = [];
        foreach ($lobs as $name => $lob) {
            $descriptors[$name] = oci_new_descriptor($this->_connection, OCI_DTYPE_LOB);
            oci_bind_by_name($stmt, ':' . $name, $descriptors[$name], -1, $lob instanceof Text ? OCI_B_CLOB : OCI_B_BLOB);
        }

        $flags = $lobs
            ? OCI_DEFAULT
            : ($this->_transactionStarted
               ? OCI_NO_AUTO_COMMIT
               : OCI_COMMIT_ON_SUCCESS
            );
        if (!$stmt ||
            !@oci_execute($stmt, $flags)) {
            $error = oci_error($stmt ?: $this->_connection);
            if ($stmt) {
                oci_free_statement($stmt);
            }
            $this->_logInfo($sql, $arg1, $name);
            $this->_logError($query, 'QUERY FAILED: ' . $error['message']);
            throw new DbException(
                $this->_errorMessage($error),
                $error['code']
            );
        }

        foreach ($lobs as $name => $lob) {
            $stream = $lob->stream;
            rewind($stream);
            while (!feof($stream)) {
                $descriptors[$name]->write(fread($stream, 8192));
            }
        }
        if ($lobs) {
            oci_commit($this->_connection);
        }

        $this->_logInfo($sql, $arg1, $name, $t->pop());
        $this->_rowCount = oci_num_rows($stmt);

        return $stmt;
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
     * @return integer  Last inserted ID.
     * @throws DbException
     */
    public function insert($sql, $arg1 = null, $arg2 = null, $pk = null,
                           $idValue = null, $sequenceName = null)
    {
        $this->execute($sql, $arg1, $arg2);
        return $idValue
            ? $idValue
            : $this->selectValue('SELECT id FROM horde_db_autoincrement');
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
     * @return integer  Last inserted ID.
     * @throws DbException
     */
    public function insertBlob($table, $fields, $pk = null, $idValue = null)
    {
        list($fields, $blobs, $locators) = $this->_prepareBlobs($fields);

        $sql = 'INSERT INTO ' . $this->quoteTableName($table) . ' ('
            . implode(
                ', ',
                array_map(array($this, 'quoteColumnName'), array_keys($fields))
            )
            . ') VALUES (' . implode(', ', $fields) . ')';

        // Protect against empty values being passed for blobs.
        if (!empty($blobs)) {
            $sql .= ' RETURNING ' . implode(', ', array_keys($blobs)) . ' INTO '
                . implode(', ', $locators);
        }

        $this->execute($sql, null, null, $blobs);

        return $idValue
            ? $idValue
            : $this->selectValue('SELECT id FROM horde_db_autoincrement');
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
        list($fields, $blobs, $locators) = $this->_prepareBlobs($fields);

        if (is_array($where)) {
            $where = $this->_replaceParameters($where[0], $where[1]);
        }

        $fnames = [];
        foreach ($fields as $field => $value) {
            $fnames[] = $this->quoteColumnName($field) . ' = ' . $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s%s',
            $this->quoteTableName($table),
            implode(', ', $fnames),
            strlen($where) ? ' WHERE ' . $where : ''
        );

        // Protect against empty values for blobs.
        if (!empty($blobs)) {
            $sql .= sprintf(' RETURNING %s INTO %s',
                implode(', ', array_keys($blobs)),
                implode(', ', $locators)
            );
        }

        $this->execute($sql, null, null, $blobs);

        return $this->_rowCount;
    }

    /**
     * Prepares a list of field values to be consumed by insertBlob() or
     * updateBlob().
     *
     * @param array $fields  A hash of column names and values. BLOB/CLOB
     *                       columns must be provided as Horde_Db_Value objects.
     *
     * @return array  A list of fields, blobs, and locators.
     */
    protected function _prepareBlobs($fields)
    {
        $blobs = $locators = [];
        foreach ($fields as $column => &$field) {
            if ($field instanceof Binary ||
                $field instanceof Text) {
                $blobs[$this->quoteColumnName($column)] = $field;
                $locators[] = ':' . $this->quoteColumnName($column);
                $field = $field instanceof Text
                    ? 'EMPTY_CLOB()'
                    : 'EMPTY_BLOB()';
            } else {
                $field = $this->quote($field);
            }
        }
        return array($fields, $blobs, $locators);
    }

    /**
     * Begins the transaction (and turns off auto-committing).
     */
    public function beginDbTransaction()
    {
        $this->_transactionStarted++;
    }

    /**
     * Commits the transaction (and turns on auto-committing).
     */
    public function commitDbTransaction()
    {
        $this->_transactionStarted--;
        if (!$this->_transactionStarted) {
            if (!oci_commit($this->_connection)) {
                $this->_handleError($this->_connection, 'commitDbTransaction');
            }
        }
    }

    /**
     * Rolls back the transaction (and turns on auto-committing). Must be
     * done if the transaction block raises an exception or returns false.
     */
    public function rollbackDbTransaction()
    {
        if (!$this->_transactionStarted) {
            return;
        }

        $this->_transactionStarted = 0;

        if (!oci_rollback($this->_connection)) {
            $this->_handleError($this->_connection, 'rollbackDbTransaction');
        }
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
        if (isset($options['limit'])) {
            $offset = isset($options['offset']) ? $options['offset'] : 0;
            $limit = $options['limit'] + $offset;
            if ($limit) {
                $sql = "SELECT a.*, ROWNUM rnum FROM ($sql) a WHERE ROWNUM <= $limit";
                if ($offset) {
                    $sql = "SELECT * FROM ($sql) WHERE rnum > $offset";
                }
            }
        }
        return $sql;
    }


    /*#########################################################################
    # Protected
    #########################################################################*/

    /**
     * Returns the Oracle name of a character set.
     *
     * @param string $charset  A charset name.
     *
     * @return string  Oracle-normalized charset.
     */
    public function _oracleCharsetName($charset)
    {
        return str_replace(
            array(
                'iso-8859-1',
                'iso-8859-2',
                'iso-8859-4',
                'iso-8859-5',
                'iso-8859-6',
                'iso-8859-7',
                'iso-8859-8',
                'iso-8859-9',
                'iso-8859-10',
                'iso-8859-13',
                'iso-8859-15',
                'shift_jis',
                'shift-jis',
                'windows-949',
                'windows-950',
                'windows-1250',
                'windows-1251',
                'windows-1252',
                'windows-1253',
                'windows-1254',
                'windows-1255',
                'windows-1256',
                'windows-1257',
                'windows-1258',
                'utf-8',
            ),
            array(
                'WE8ISO8859P1',
                'EE8ISO8859P2',
                'NEE8ISO8859P4',
                'CL8ISO8859P5',
                'AR8ISO8859P6',
                'EL8ISO8859P7',
                'IW8ISO8859P8',
                'WE8ISO8859P9',
                'NE8ISO8859P10',
                'BLT8ISO8859P13',
                'WE8ISO8859P15',
                'JA16SJIS',
                'JA16SJIS',
                'KO16MSWIN949',
                'ZHT16MSWIN950',
                'EE8MSWIN1250',
                'CL8MSWIN1251',
                'WE8MSWIN1252',
                'EL8MSWIN1253',
                'TR8MSWIN1254',
                'IW8MSWIN1255',
                'AR8MSWIN1256',
                'BLT8MSWIN1257',
                'VN8MSWIN1258',
                'AL32UTF8',
            ),
            Horde_String::lower($charset)
        );
    }

    /**
     * Creates a formatted error message from a oci_error() result hash.
     *
     * @param array $error  Hash returned from oci_error().
     *
     * @return string  The formatted error message.
     */
    protected function _errorMessage($error)
    {
        return 'QUERY FAILED: ' . $error['message']
            . "\n\nat offset " . $error['offset']
            . "\n" . $error['sqltext'];
    }

    /**
     * Log and throws an exception for the last error.
     *
     * @param resource $resource  The resource (connection or statement) to
     *                            call oci_error() upon.
     * @param string $method      The calling method.
     *
     * @throws DbException
     */
    protected function _handleError($resource, $method)
    {
        $error = oci_error($resource);
        $this->_logError(
            $error['message'],
            'Horde_Db_Adapter_Oci8::' . $method. '()'
        );
        throw new DbException(
            $this->_errorMessage($error),
            $error['code']
        );
    }
}
