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

use Horde\Db\Adapter\Postgresql\Schema as PostgresqlSchema;
use Horde\Db\DbException;
use Horde\Db\Adapter\Postgresql\Column;

/**
 * PDO_PostgreSQL Horde_Db_Adapter
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
class Pgsql extends Base
{
    /**
     * @var string
     */
    protected $schemaClass = PostgresqlSchema::class;

    /**
     * @return  string
     */
    public function adapterName()
    {
        return 'PDO_PostgreSQL';
    }

    /**
     * @return  boolean
     */
    public function supportsMigrations()
    {
        return true;
    }

    /**
     * Does PostgreSQL support standard conforming strings?
     * @return  boolean
     */
    public function supportsStandardConformingStrings()
    {
        // Temporarily set the client message level above error to prevent unintentional
        // error messages in the logs when working on a PostgreSQL database server that
        // does not support standard conforming strings.
        $clientMinMessagesOld = $this->schema->getClientMinMessages();
        $this->schema->setClientMinMessages('panic');

        $hasSupport = $this->selectValue('SHOW standard_conforming_strings');

        $this->schema->setClientMinMessages($clientMinMessagesOld);
        return $hasSupport;
    }

    public function supportsInsertWithReturning()
    {
        return true;
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

        $this->lastQuery = $sql = "SET datestyle TO 'iso'";
        $retval = $this->connection->exec($sql);
        if ($retval === false) {
            $error = $this->connection->errorInfo();
            throw new DbException($error[2]);
        }

        $this->configureConnection();
    }


    /*##########################################################################
    # Database Statements
    ##########################################################################*/

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
        // Extract the table from the insert sql. Yuck.
        $temp = explode(' ', trim($sql), 4);
        $table = str_replace('"', '', $temp[2]);

        // Try an insert with 'returning id'
        if (!$pk) {
            list($pk, $sequenceName) = $this->schema->pkAndSequenceFor($table);
        }
        if ($pk) {
            $id = $this->selectValue($sql . ' RETURNING ' . $this->quoteColumnName($pk), $arg1, $arg2);
            $this->schema->resetPkSequence($table, $pk, $sequenceName);
            return $id;
        }

        // If neither pk nor sequence name is given, look them up.
        if (!($pk || $sequenceName)) {
            list($pk, $sequenceName) = $this->schema->pkAndSequenceFor($table);
        }

        // Otherwise, insert then grab last_insert_id.
        $this->execute($sql, $arg1, $arg2);
        if ($idValue) {
            return $idValue;
        }

        // If a pk is given, fallback to default sequence name.
        // Don't fetch last insert id for a table without a pk.
        if ($pk &&
            ($sequenceName ||
             $sequenceName = $this->schema->defaultSequenceName($table, $pk))) {
            $this->schema->resetPkSequence($table, $pk, $sequenceName);
            return $this->lastInsertId($table, $sequenceName);
        }
        return 0;
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
            $sql .= " LIMIT $limit";
        }
        if (isset($options['offset']) && $offset = $options['offset']) {
            $sql .= " OFFSET $offset";
        }
        return $sql;
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    /**
     * Parse configuration array into options for PDO constructor.
     *
     * @throws  DbException
     * @return  array  [dsn, username, password]
     */
    protected function parseConfig()
    {
        $this->config['adapter'] = 'pgsql';

        if (!empty($this->config['socket']) && !empty($this->config['host'])) {
            throw new DbException('Can only specify host or socket, not both');
        }

        // PDO for PostgreSQL does not accept a socket argument
        // in the connection string; the location can be set via the
        // "host" argument instead.
        if (!empty($this->config['socket'])) {
            $this->config['host'] = $this->config['socket'];
            unset($this->config['socket']);
        }

        return parent::parseConfig();
    }

    /**
     * Configures the encoding, verbosity, and schema search path of the connection.
     * This is called by connect() and should not be called manually.
     */
    protected function configureConnection()
    {
        if (!empty($this->config['charset'])) {
            $this->lastQuery = $sql = 'SET client_encoding TO '.$this->quoteString($this->config['charset']);
            $this->execute($sql);
        }

        if (!empty($this->config['client_min_messages'])) {
            $this->schema->setClientMinMessages($this->config['client_min_messages']);
        }
        $this->schema->setSchemaSearchPath(!empty($this->config['schema_search_path']) || !empty($this->config['schema_order']));
    }

    /**
     * @TODO
     */
    protected function selectRaw($sql, $arg1=null, $arg2=null)
    {
        $rows = [];
        $result = $this->execute($sql, $arg1, $arg2);
        if (!$result) {
            return [];
        }

        $moneyFields = [];
        for ($i = 0, $i_max = $result->columnCount(); $i < $i_max; $i++) {
            $f = $result->getColumnMeta($i);
            if (!empty($f['pgsql:oid']) && $f['pgsql:oid'] == Column::MONEY_COLUMN_TYPE_OID) {
                $moneyFields[] = $i;
                $moneyFields[] = $f['name'];
            }
        }

        foreach ($result as $row) {
            // If this is a money type column and there are any currency
            // symbols, then strip them off. Indeed it would be prettier to do
            // this in Horde_Db_Adapter_Postgres_Column::stringToDecimal but
            // would break form input fields that call valueBeforeTypeCast.
            foreach ($moneyFields as $f) {
                // Because money output is formatted according to the locale, there are two
                // cases to consider (note the decimal separators):
                //  (1) $12,345,678.12
                //  (2) $12.345.678,12
                if (preg_match('/^-?\D+[\d,]+\.\d{2}$/', $row[$f])) { // #1
                    $row[$f] = preg_replace('/[^-\d\.]/', '', $row[$f]) . "\n";
                } elseif (preg_match('/^-?\D+[\d\.]+,\d{2}$/', $row[$f])) { // #2
                    $row[$f] = str_replace(',', '.', preg_replace('/[^-\d,]/', '', $row[$f])) . "\n";
                }
            }
            $rows[] = $row;
        }

        $result->closeCursor();
        return $rows;
    }

    /**
     * Returns the current ID of a table's sequence.
     *
     * NOTE: This requires that the sequence was already used to INSERT a value
     *       in this session.
     *
     * @todo Remove unused $table parameter.
     */
    protected function lastInsertId($table, $sequenceName)
    {
        return (int)$this->selectValue('SELECT currval('.$this->schema->quoteSequenceName($sequenceName).')');
    }
}
