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
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Adapter
 */
namespace Horde\Db\Adapter\Postgresql;
use \Horde\Db\Adapter;
use \Horde\Db\Adapter\Base\Schema as BaseSchema;
use \Horde\Db\Adapter\Base\Index;
use \Exception;
use \InvalidArgumentException;
use \Horde\Db\DbException;

/**
 * Class for PostgreSQL-specific managing of database schemes and handling of
 * SQL dialects and quoting.
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @copyright  2007 Maintainable Software, LLC
 * @copyright  2008-2021 Horde LLC
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Adapter
 */
class Schema extends BaseSchema
{
    /**
     * The active schema search path.
     *
     * @var string
     */
    protected $schemaSearchPath = '';

    /**
     * Cached version.
     *
     * @var int
     */
    protected $version;


    /*##########################################################################
    # Object factories
    ##########################################################################*/

    /**
     * Factory for Column objects.
     *
     * @param string $name     The column's name, such as "supplier_id" in
     *                         "supplier_id int(11)".
     * @param string $default  The type-casted default value, such as "new" in
     *                         "sales_stage varchar(20) default 'new'".
     * @param string $sqlType  Used to extract the column's type, length and
     *                         signed status, if necessary. For example
     *                         "varchar" and "60" in "company_name varchar(60)"
     *                         or "unsigned => true" in "int(10) UNSIGNED".
     * @param bool $null    Whether this column allows NULL values.
     *
     * @return Column  A column object.
     */
    public function makeColumn($name, $default, $sqlType = null, $null = true)
    {
        return new Column($name, $default, $sqlType, $null);
    }


    /*##########################################################################
    # Quoting
    ##########################################################################*/

    /**
     * Quotes the column value to help prevent SQL injection attacks.
     *
     * This method makes educated guesses on the scalar type based on the
     * passed value. Make sure to correctly cast the value and/or pass the
     * $column parameter to get the best results.
     *
     * @param mixed $value    The scalar value to quote, a Horde_Db_Value,
     *                        Horde_Date, or DateTime instance, or an object
     *                        implementing quotedId().
     * @param object $column  An object implementing getType().
     *
     * @return string  The correctly quoted value.
     */
    public function quote($value, $column = null)
    {
        if (!$column) {
            return parent::quote($value, $column);
        }

        if (is_string($value) &&
            $column->getType() == 'binary') {
            return $this->quoteBinary($value);
        }
        if (is_string($value) && $column->getSqlType() == 'xml') {
            return "xml '" . $this->quoteString($value) . "'";
        }
        if (is_numeric($value) && $column->getSqlType() == 'money') {
            // Not truly string input, so doesn't require (or allow) escape
            // string syntax.
            return "'" . $value . "'";
        }
        if (is_string($value) && substr($column->getSqlType(), 0, 3) == 'bit') {
            if (preg_match('/^[0-9A-F]*$/i', $value)) {
                // Hexadecimal notation
                return "X'" . $value . "'";
            }
            if (preg_match('/^[01]*$/', $value)) {
                // Bit-string notation
                return "B'" . $value . "'";
            }
        }

        return parent::quote($value, $column);
    }

    /**
     * Returns a quoted sequence name.
     *
     * PostgreSQL specific method.
     *
     * @param string $name  A sequence name.
     *
     * @return string  The quoted sequence name.
     */
    public function quoteSequenceName($name)
    {
        return '\'' . str_replace('"', '""', $name) . '\'';
    }

    /**
     * Returns a quoted boolean true.
     *
     * @return string  The quoted boolean true.
     */
    public function quoteTrue()
    {
        return "'t'";
    }

    /**
     * Returns a quoted boolean false.
     *
     * @return string  The quoted boolean false.
     */
    public function quoteFalse()
    {
        return "'f'";
    }

    /**
     * Returns a quoted binary value.
     *
     * @param mixed  A binary value.
     *
     * @return string  The quoted binary value.
     */
    public function quoteBinary($value)
    {
        if ($this->postgresqlVersion() >= 90000) {
            return "E'\\\\x" . bin2hex($value) . "'";
        }

        /* MUST escape zero octet(0), single quote (39), and backslash (92).
         * MAY escape non-printable octets, but they are required in some
         * instances so it is best to escape all. */
        return "E'" . preg_replace_callback("/[\\x00-\\x1f\\x27\\x5c\\x7f-\\xff]/", array($this, '_quoteBinaryCallback'), $value) . "'";
    }

    /**
     * Callback function for quoteBinary().
     *
     * @param array $matches  Matches from preg_replace().
     *
     * @return string  Escaped/encoded binary value.
     */
    protected function quoteBinaryCallback($matches)
    {
        return sprintf('\\\\%03.o', ord($matches[0]));
    }


    /*##########################################################################
    # Schema Statements
    ##########################################################################*/

    /**
     * Returns a hash of mappings from the abstract data types to the native
     * database types.
     *
     * See TableDefinition::column() for details on the recognized abstract
     * data types.
     *
     * @see TableDefinition::column()
     *
     * @return array  A database type map.
     */
    public function nativeDatabaseTypes()
    {
        return array(
            'autoincrementKey' => 'serial primary key',
            'string'           => array('name' => 'character varying',
                                        'limit' => 255),
            'text'             => array('name' => 'text',
                                        'limit' => null),
            'mediumtext'       => array('name' => 'text',
                                        'limit' => null),
            'longtext'         => array('name' => 'text',
                                        'limit' => null),
            'integer'          => array('name' => 'integer',
                                        'limit' => null),
            'float'            => array('name' => 'float',
                                        'limit' => null),
            'decimal'          => array('name' => 'decimal',
                                        'limit' => null),
            'datetime'         => array('name' => 'timestamp',
                                        'limit' => null),
            'timestamp'        => array('name' => 'timestamp',
                                        'limit' => null),
            'time'             => array('name' => 'time',
                                        'limit' => null),
            'date'             => array('name' => 'date',
                                        'limit' => null),
            'binary'           => array('name' => 'bytea',
                                        'limit' => null),
            'boolean'          => array('name' => 'boolean',
                                        'limit' => null),
        );
    }

    /**
     * Returns the maximum length a table alias can have.
     *
     * Returns the configured supported identifier length supported by
     * PostgreSQL.
     *
     * @return int  The maximum table alias length.
     */
    public function tableAliasLength()
    {
        return (int)$this->adapter->selectValue('SHOW max_identifier_length');
    }

    /**
     * Returns a list of all tables in the schema search path.
     *
     * @return array  A table list.
     */
    public function tables()
    {
        return $this->adapter->selectValues('SELECT table_name FROM information_schema.tables WHERE table_schema = ANY (CURRENT_SCHEMAS(false));');
    }

    /**
     * Returns a table's primary key.
     *
     * @param string $tableName  A table name.
     * @param string $name       (can be removed?)
     *
     * @return Index  The primary key index object.
     */
    public function primaryKey($tableName, $name = null)
    {
        $sql = '
            SELECT column_name
            FROM information_schema.constraint_column_usage
            WHERE table_name = ?
                AND constraint_name = (SELECT constraint_name
                                       FROM information_schema.table_constraints
                                       WHERE table_name = ?
                                           AND constraint_type = ?)';
        $pk = $this->adapter->selectValues($sql,
                                  array($tableName, $tableName, 'PRIMARY KEY'),
                                  $name);

        return $this->makeIndex($tableName, 'PRIMARY', true, true, $pk);
    }

    /**
     * Returns a list of tables indexes.
     *
     * @param string $tableName  A table name.
     * @param string $name       (can be removed?)
     *
     * @return array  A list of Horde_Db_Adapter_Base_Index objects.
     */
    public function indexes($tableName, $name = null)
    {
        $indexes = @unserialize($this->adapter->cacheRead("tables/indexes/$tableName"));

        if (!$indexes) {

            $sql = "
              SELECT distinct i.relname, d.indisunique, a.attname
                 FROM pg_class t, pg_class i, pg_index d, pg_attribute a
              WHERE i.relkind = 'i'
                 AND d.indexrelid = i.oid
                 AND d.indisprimary = 'f'
                 AND t.oid = d.indrelid
                 AND t.relname = " . $this->quote($tableName) . "
                 AND i.relnamespace IN (SELECT oid FROM pg_namespace WHERE nspname = ANY(CURRENT_SCHEMAS(false)))
                 AND a.attrelid = t.oid
                 AND (d.indkey[0] = a.attnum OR d.indkey[1] = a.attnum
                   OR d.indkey[2] = a.attnum OR d.indkey[3] = a.attnum
                   OR d.indkey[4] = a.attnum OR d.indkey[5] = a.attnum
                   OR d.indkey[6] = a.attnum OR d.indkey[7] = a.attnum
                   OR d.indkey[8] = a.attnum OR d.indkey[9] = a.attnum)
              ORDER BY i.relname";

            $result = $this->adapter->select($sql, $name);

            $currentIndex = null;
            $indexes = [];

            foreach ($result as $row) {
                if ($currentIndex != $row['relname']) {
                    $currentIndex = $row['relname'];
                    $indexes[] = $this->makeIndex(
                        $tableName, $row['relname'], false, $row['indisunique'] == 't', array());
                }
                $indexes[count($indexes) - 1]->columns[] = $row['attname'];
            }

            $this->adapter->cacheWrite("tables/indexes/$tableName", serialize($indexes));
        }

        return $indexes;
    }

    /**
     * Returns a list of table columns.
     *
     * @param string $tableName  A table name.
     * @param string $name       (can be removed?)
     *
     * @return array  A list of Horde_Db_Adapter_Base_Column objects.
     */
    public function columns($tableName, $name = null)
    {
        $rows = @unserialize($this->adapter->cacheRead("tables/columns/$tableName"));

        if (!$rows) {
            $rows = $this->columnDefinitions($tableName, $name);

            $this->adapter->cacheWrite("tables/columns/$tableName", serialize($rows));
        }

        // Create columns from rows.
        $columns = [];
        foreach ($rows as $row) {
            $columns[$row['attname']] = $this->makeColumn(
                $row['attname'], $row['adsrc'], $row['format_type'], !(boolean)$row['attnotnull']);
        }
        return $columns;
    }

    /**
     * Returns the list of a table's column names, data types, and default
     * values.
     *
     * The underlying query is roughly:
     *   SELECT column.name, column.type, default.value
     *    FROM column LEFT JOIN default
     *      ON column.table_id = default.table_id
     *     AND column.num = default.column_num
     *   WHERE column.table_id = get_table_id('table_name')
     *     AND column.num > 0
     *     AND NOT column.is_dropped
     *   ORDER BY column.num
     *
     * If the table name is not prefixed with a schema, the database will take
     * the first match from the schema search path.
     *
     * Query implementation notes:
     *  - format_type includes the column size constraint, e.g. varchar(50)
     *  - ::regclass is a function that gives the id for a table name
     */
    protected function columnDefinitions($tableName, $name = null)
    {
        /* @todo See if we can get this from information_schema instead */
        return $this->adapter->selectAll('
          SELECT a.attname, format_type(a.atttypid, a.atttypmod),
            pg_get_expr(d.adbin, d.adrelid) AS adsrc, a.attnotnull
          FROM pg_attribute a
          LEFT JOIN pg_attrdef d ON a.attrelid = d.adrelid AND a.attnum = d.adnum
          WHERE a.attrelid = ' . $this->quote($tableName) . '::regclass
            AND a.attnum > 0 AND NOT a.attisdropped
          ORDER BY a.attnum;', $name);
    }

    /**
     * Renames a table.
     *
     * @param string $name     A table name.
     * @param string $newName  The new table name.
     */
    public function renameTable($name, $newName)
    {
        $this->clearTableCache($name);

        return $this->adapter->execute(sprintf('ALTER TABLE %s RENAME TO %s', $this->quoteTableName($name), $this->quoteTableName($newName)));
    }

    /**
     * Adds a new column to a table.
     *
     * @param string $tableName   A table name.
     * @param string $columnName  A column name.
     * @param string $type        A data type.
     * @param array $options      Column options. See
     *                            Horde_Db_Adapter_Base_TableDefinition#column()
     *                            for details.
     */
    public function addColumn($tableName, $columnName, $type,
                              $options = [])
    {
        $this->clearTableCache($tableName);

        $options = array_merge(
            array('autoincrement' => null,
                  'limit'         => null,
                  'precision'     => null,
                  'scale'         => null),
            $options);

        $sqltype = $this->typeToSql($type, $options['limit'],
                                    $options['precision'], $options['scale']);

        /* Convert to SERIAL type if needed. */
        if ($options['autoincrement']) {
            switch ($sqltype) {
            case 'bigint':
                $sqltype = 'BIGSERIAL';
                break;

            case 'integer':
            default:
                $sqltype = 'SERIAL';
                break;
            }
        }

        // Add the column.
        $sql = sprintf('ALTER TABLE %s ADD COLUMN %s %s',
                       $this->quoteTableName($tableName),
                       $this->quoteColumnName($columnName),
                       $sqltype);
        $this->adapter->execute($sql);

        if (array_key_exists('default', $options)) {
            $sql = sprintf('UPDATE %s SET %s = %s',
                           $this->quoteTableName($tableName),
                           $this->quoteColumnName($columnName),
                           $this->quote($options['default']));
            $this->adapter->execute($sql);
            $this->changeColumnDefault($tableName, $columnName,
                                       $options['default']);
        }

        if (isset($options['null']) && $options['null'] === false) {
            $this->changeColumnNull(
                $tableName, $columnName, false,
                isset($options['default']) ? $options['default'] : null);
        }
    }

    /**
     * Changes an existing column's definition.
     *
     * @param string $tableName   A table name.
     * @param string $columnName  A column name.
     * @param string $type        A data type.
     * @param array $options      Column options. See
     *                            Horde_Db_Adapter_Base_TableDefinition#column()
     *                            for details.
     */
    public function changeColumn($tableName, $columnName, $type,
                                 $options = [])
    {
        $this->clearTableCache($tableName);

        $options = array_merge(
            array('autoincrement' => null,
                  'limit'         => null,
                  'precision'     => null,
                  'scale'         => null),
            $options);

        $quotedTableName = $this->quoteTableName($tableName);

        $primaryKey = $type == 'autoincrementKey';
        if ($primaryKey) {
            $type = 'integer';
            $options['autoincrement'] = true;
            $options['limit'] = $options['precision'] = $options['scale'] = null;
            try {
                $this->removePrimaryKey($tableName);
            } catch (DbException $e) {
            }
        }

        $sql = sprintf('ALTER TABLE %s ALTER COLUMN %s TYPE %s',
                       $quotedTableName,
                       $this->quoteColumnName($columnName),
                       $this->typeToSql($type,
                                        $options['limit'],
                                        $options['precision'],
                                        $options['scale']));
        try {
            $this->adapter->execute($sql);
        } catch (DbException $e) {
            // This is PostgreSQL 7.x, or the old type could not be coerced to
            // the new type, so we have to use a more arcane way of doing it.
            try {
                // Booleans can't always be cast to other data types; do extra
                // work to handle them.
                $oldType = $this->column($tableName, $columnName)->getType();

                $this->adapter->beginDbTransaction();

                $tmpColumnName = $columnName.'_change_tmp';
                $this->addColumn($tableName, $tmpColumnName, $type, $options);

                if ($oldType == 'boolean') {
                    $sql = sprintf('UPDATE %s SET %s = CAST(CASE WHEN %s IS TRUE THEN 1 ELSE 0 END AS %s)',
                                   $quotedTableName,
                                   $this->quoteColumnName($tmpColumnName),
                                   $this->quoteColumnName($columnName),
                                   $this->typeToSql($type,
                                                    $options['limit'],
                                                    $options['precision'],
                                                    $options['scale']));
                } else {
                    $sql = sprintf('UPDATE %s SET %s = CAST(%s AS %s)',
                                   $quotedTableName,
                                   $this->quoteColumnName($tmpColumnName),
                                   $this->quoteColumnName($columnName),
                                   $this->typeToSql($type,
                                                    $options['limit'],
                                                    $options['precision'],
                                                    $options['scale']));
                }
                $this->adapter->execute($sql);
                $this->removeColumn($tableName, $columnName);
                $this->renameColumn($tableName, $tmpColumnName, $columnName);

                $this->adapter->commitDbTransaction();
            } catch (DbException $e) {
                $this->adapter->rollbackDbTransaction();
                throw $e;
            }
        }

        if ($options['autoincrement']) {
            $seq_name = $this->defaultSequenceName($tableName, $columnName);
            try {
                $this->adapter->execute('DROP SEQUENCE ' . $seq_name . ' CASCADE');
            } catch (DbException $e) {}
            $this->adapter->execute('CREATE SEQUENCE ' . $seq_name);
            $this->resetPkSequence($tableName, $columnName, $seq_name);

            /* Can't use changeColumnDefault() since it quotes the
             * default value (NEXTVAL is a postgres keyword, not a text
             * value). */
            $this->clearTableCache($tableName);
            $sql = sprintf('ALTER TABLE %s ALTER COLUMN %s SET DEFAULT NEXTVAL(%s)',
                           $this->quoteTableName($tableName),
                           $this->quoteColumnName($columnName),
                           $this->quoteSequenceName($seq_name));
            $this->adapter->execute($sql);
            $sql = sprintf('ALTER SEQUENCE %s OWNED BY %s.%s',
                           $seq_name,
                           $this->quoteTableName($tableName),
                           $this->quoteColumnName($columnName));
            $this->adapter->execute($sql);
        } elseif (array_key_exists('default', $options)) {
            $this->changeColumnDefault($tableName, $columnName,
                                       $options['default']);
        }

        if ($primaryKey) {
            $this->addPrimaryKey($tableName, $columnName);
        }

        if (array_key_exists('null', $options)) {
            $this->changeColumnNull(
                $tableName, $columnName, $options['null'],
                isset($options['default']) ? $options['default'] : null);
        }
    }

    /**
     * Sets a new default value for a column.
     *
     * If you want to set the default value to NULL, you are out of luck. You
     * need to execute the apppropriate SQL statement yourself.
     *
     * @param string $tableName   A table name.
     * @param string $columnName  A column name.
     * @param mixed $default      The new default value.
     */
    public function changeColumnDefault($tableName, $columnName, $default)
    {
        $this->clearTableCache($tableName);
        $sql = sprintf('ALTER TABLE %s ALTER COLUMN %s SET DEFAULT %s',
                       $this->quoteTableName($tableName),
                       $this->quoteColumnName($columnName),
                       $this->quote($default));
        return $this->adapter->execute($sql);
    }

    /**
     * Sets whether a column allows NULL values.
     *
     * @param string $tableName   A table name.
     * @param string $columnName  A column name.
     * @param bool $null       Whether NULL values are allowed.
     * @param mixed $default      The new default value.
     */
    public function changeColumnNull($tableName, $columnName, $null,
                                     $default = null)
    {
        $this->clearTableCache($tableName);
        if (!$null && !is_null($default)) {
            $sql = sprintf('UPDATE %s SET %s = %s WHERE %s IS NULL',
                           $this->quoteTableName($tableName),
                           $this->quoteColumnName($columnName),
                           $this->quote($default),
                           $this->quoteColumnName($columnName));
            $this->adapter->execute($sql);
        }
        $sql = sprintf('ALTER TABLE %s ALTER %s %s NOT NULL',
                       $this->quoteTableName($tableName),
                       $this->quoteColumnName($columnName),
                       $null ? 'DROP' : 'SET');
        return $this->adapter->execute($sql);
    }

    /**
     * Renames a column.
     *
     * @param string $tableName      A table name.
     * @param string $columnName     A column name.
     * @param string $newColumnName  The new column name.
     */
    public function renameColumn($tableName, $columnName, $newColumnName)
    {
        $this->clearTableCache($tableName);
        $sql = sprintf('ALTER TABLE %s RENAME COLUMN %s TO %s',
                       $this->quoteTableName($tableName),
                       $this->quoteColumnName($columnName),
                       $this->quoteColumnName($newColumnName));
        return $this->adapter->execute($sql);
    }

    /**
     * Removes a primary key from a table.
     *
     * @param string $tableName  A table name.
     *
     * @throws DbException
     */
    public function removePrimaryKey($tableName)
    {
        $this->clearTableCache($tableName);
        $keyName = $this->adapter->selectValue(
            'SELECT constraint_name
             FROM information_schema.table_constraints
             WHERE table_name = ?
                 AND constraint_type = ?',
            array($tableName, 'PRIMARY KEY'));
        if ($keyName) {
            $sql = sprintf('ALTER TABLE %s DROP CONSTRAINT %s CASCADE',
                           $this->quoteTableName($tableName),
                           $this->quoteColumnName($keyName));
            return $this->adapter->execute($sql);
        }
    }

    /**
     * Removes an index from a table.
     *
     * See parent class for examples.
     *
     * @param string $tableName      A table name.
     * @param string|array $options  Either a column name or index options:
     *                               - name: (string) the index name.
     *                               - column: (string|array) column name(s).
     */
    public function removeIndex($tableName, $options = [])
    {
        $this->clearTableCache($tableName);
        return $this->adapter->execute('DROP INDEX ' . $this->indexName($tableName, $options));
    }

    /**
     * Creates a database.
     *
     * @param string $name    A database name.
     * @param array $options  Database options: owner, template, charset,
     *                        tablespace, and connection_limit.
     */
    public function createDatabase($name, $options = [])
    {
        $options = array_merge(array('charset' => 'utf8'), $options);

        $optionString = '';
        foreach ($options as $key => $value) {
            switch ($key) {
            case 'owner':
                $optionString .= " OWNER = '$value'";
                break;
            case 'template':
                $optionString .= " TEMPLATE = $value";
                break;
            case 'charset':
                $optionString .= " ENCODING = '$value'";
                break;
            case 'tablespace':
                $optionString .= " TABLESPACE = $value";
                break;
            case 'connection_limit':
                $optionString .= " CONNECTION LIMIT = $value";
            }
        }

        return $this->adapter->execute('CREATE DATABASE ' . $this->quoteTableName($name) . $optionString);
    }

    /**
     * Drops a database.
     *
     * @param string $name  A database name.
     */
    public function dropDatabase($name)
    {
        return $this->adapter->execute('DROP DATABASE IF EXISTS ' . $this->quoteTableName($name));
    }

    /**
     * Returns the name of the currently selected database.
     *
     * @return string  The database name.
     */
    public function currentDatabase()
    {
        return $this->adapter->selectValue('SELECT current_database()');
    }

    /**
     * Generates the SQL definition for a column type.
     *
     * @param string $type        A column type.
     * @param integer $limit      Maximum column length (non decimal type only)
     * @param integer $precision  The number precision (decimal type only).
     * @param integer $scale      The number scaling (decimal columns only).
     * @param bool $unsigned   Whether the column is an unsigned number
     *                            (non decimal columns only).
     *
     * @return string  The SQL definition. If $type is not one of the
     *                 internally supported types, $type is returned unchanged.
     */
    public function typeToSql($type, $limit = null, $precision = null,
                              $scale = null, $unsigned = null)
    {
        if ($type != 'integer') {
            return parent::typeToSql($type, $limit, $precision, $scale);
        }

        switch ($limit) {
        case 1:
        case 2:
            return 'smallint';

        case 3:
        case 4:
        case null:
            return 'integer';

        case 5:
        case 6:
        case 7:
        case 8:
            return 'bigint';
        }

        throw new DbException("No integer type has byte size $limit. Use a numeric with precision 0 instead.");
    }

    /**
     * Generates a DISTINCT clause for SELECT queries.
     *
     * PostgreSQL requires the ORDER BY columns in the SELECT list for distinct
     * queries, and requires that the ORDER BY include the DISTINCT column.
     *
     * <code>
     * $connection->distinct('posts.id', 'posts.created_at DESC')
     * </code>
     *
     * @param string $columns  A column list.
     * @param string $orderBy  An ORDER clause.
     *
     * @return string  The generated DISTINCT clause.
     */
    public function distinct($columns, $orderBy = null)
    {
        if (empty($orderBy)) {
            return 'DISTINCT ' . $columns;
        }

        // Construct a clean list of column names from the ORDER BY clause,
        // removing any ASC/DESC modifiers.
        $orderColumns = [];
        foreach (preg_split('/\s*,\s*/', $orderBy, -1, PREG_SPLIT_NO_EMPTY) as $orderByClause) {
            $orderColumns[] = current(preg_split('/\s+/', $orderByClause, -1, PREG_SPLIT_NO_EMPTY)) . ' AS alias_' . count($orderColumns);
        }

        // Return a DISTINCT ON() clause that's distinct on the columns we want
        // but includes all the required columns for the ORDER BY to work
        // properly.
        return sprintf('DISTINCT ON (%s) %s, %s',
                       $columns, $columns, implode(', ', $orderColumns));
    }

    /**
     * Adds an ORDER BY clause to an existing query.
     *
     * PostgreSQL does not allow arbitrary ordering when using DISTINCT ON, so
     * we work around this by wrapping the $sql string as a sub-select and
     * ordering in that query.
     *
     * @param string $sql     An SQL query to manipulate.
     * @param array $options  Options:
     *                        - order: Order column an direction.
     *
     * @return string  The manipulated SQL query.
     */
    public function addOrderByForAssociationLimiting($sql, $options)
    {
        if (empty($options['order'])) {
            return $sql;
        }

        $order = [];
        foreach (preg_split('/\s*,\s*/', $options['order'], -1, PREG_SPLIT_NO_EMPTY) as $s) {
            if (preg_match('/\bdesc$/i', $s)) {
                $s = 'DESC';
            }
            $order[] = 'id_list.alias_' . count($order) . ' ' . $s;
        }
        $order = implode(', ', $order);

        return sprintf('SELECT * FROM (%s) AS id_list ORDER BY %s',
                       $sql, $order);
    }

    /**
     * Generates an INTERVAL clause for SELECT queries.
     *
     * @param string $interval   The interval.
     * @param string $precision  The precision.
     *
     * @return string  The generated INTERVAL clause.
     */
    public function interval($interval, $precision)
    {
        return 'INTERVAL \'' . $interval . ' ' . $precision . '\'';
    }

    /**
     * Generates a modified date for SELECT queries.
     *
     * @param string $reference  The reference date - this is a column
     *                           referenced in the SELECT.
     * @param string $operator   Add or subtract time? (+/-)
     * @param integer $amount    The shift amount (number of days if $interval
     *                           is DAY, etc).
     * @param string $interval   The interval (SECOND, MINUTE, HOUR, DAY,
     *                           MONTH, YEAR).
     *
     * @return string  The generated INTERVAL clause.
     */
    public function modifyDate($reference, $operator, $amount, $interval)
    {
        if (!is_int($amount)) {
            throw new InvalidArgumentException('$amount parameter must be an integer');
        }
        return sprintf('%s %s INTERVAL \'%s %s\'',
                       $reference,
                       $operator,
                       $amount,
                       $interval);
    }

    /**
     * Returns an expression using the specified operator.
     *
     * @param string $lhs    The column or expression to test.
     * @param string $op     The operator.
     * @param string $rhs    The comparison value.
     * @param bool $bind  If true, the method returns the query and a list
     *                       of values suitable for binding as an array.
     * @param array $params  Any additional parameters for the operator.
     *
     * @return string|array  The SQL test fragment, or an array containing the
     *                       query and a list of values if $bind is true.
     */
    public function buildClause($lhs, $op, $rhs, $bind = false,
                                $params = [])
    {
        $lhs = $this->escapePrepare($lhs);
        switch ($op) {
        case '|':
        case '&':
            /* Only PgSQL 7.3+ understands SQL99 'SIMILAR TO'; use ~ for
             * greater backwards compatibility. */
            $query = 'CASE WHEN CAST(%s AS VARCHAR) ~ \'^-?[0-9]+$\' THEN (CAST(%s AS INTEGER) %s %s) ELSE 0 END';
            if ($bind) {
                return array(sprintf($query, $lhs, $lhs, $op, '?'),
                             array((int)$rhs));
            } else {
                return sprintf($query, $lhs, $lhs, $op, (int)$rhs);
            }

        case 'LIKE':
            $query = '%s ILIKE %s';
            if ($bind) {
                if (empty($params['begin'])) {
                    return array(sprintf($query, $lhs, '?'),
                                 array('%' . $rhs . '%'));
                }
                return array(sprintf('(' . $query . ' OR ' . $query . ')',
                                     $lhs, '?', $lhs, '?'),
                             array($rhs . '%', '% ' . $rhs . '%'));
            }
            if (empty($params['begin'])) {
                return sprintf($query,
                               $lhs,
                               $this->escapePrepare($this->quote('%' . $rhs . '%')));
            }
            return sprintf('(' . $query . ' OR ' . $query . ')',
                           $lhs,
                           $this->escapePrepare($this->quote($rhs . '%')),
                           $lhs,
                           $this->escapePrepare($this->quote('% ' . $rhs . '%')));
        }

        return parent::buildClause($lhs, $op, $rhs, $bind, $params);
    }


    /*##########################################################################
    # PostgreSQL specific methods
    ##########################################################################*/

    /**
     * Returns the current database's encoding format.
     *
     * @return string  The current database's encoding format.
     */
    public function encoding()
    {
        return $this->adapter->selectValue(
            'SELECT pg_encoding_to_char(pg_database.encoding) FROM pg_database
             WHERE pg_database.datname LIKE ' . $this->quote($this->currentDatabase()));
    }

    /**
     * Sets the schema search path to a string of comma-separated schema names.
     *
     * Names beginning with $ have to be quoted (e.g. $user => '$user').  See:
     * http://www.postgresql.org/docs/current/static/ddl-schemas.html
     *
     * @param string $schemaCsv  A comma-separated schema name list.
     */
    public function setSchemaSearchPath($schemaCsv)
    {
        if ($schemaCsv) {
            $this->adapter->execute('SET search_path TO ' . $schemaCsv);
            $this->schemaSearchPath = $schemaCsv;
        }
    }

    /**
     * Returns the current client log message level.
     *
     * @return string  The current client log message level.
     */
    public function getClientMinMessages()
    {
        return $this->adapter->selectValue('SHOW client_min_messages');
    }

    /**
     * Sets the client log message level.
     *
     * @param string $level  The client log message level. One of DEBUG5,
     *                       DEBUG4, DEBUG3, DEBUG2, DEBUG1, LOG, NOTICE,
     *                       WARNING, ERROR, FATAL, or PANIC.
     */
    public function setClientMinMessages($level)
    {
        return $this->adapter->execute('SET client_min_messages TO ' . $this->quote($level));
    }

    /**
     * Returns the sequence name for a table's primary key or some other
     * specified key.
     *
     * If a sequence name doesn't exist, it is built from the table and primary
     * key name.
     *
     * @param string $tableName  A table name.
     * @param string $pk         A primary key name. Overrides the existing key
     *                           name when building a new sequence name.
     *
     * @return string  The key's sequence name.
     */
    public function defaultSequenceName($tableName, $pk = null)
    {
        list($defaultPk, $defaultSeq) = $this->pkAndSequenceFor($tableName);
        if (!$defaultSeq) {
            $defaultSeq = $tableName . '_' . ($pk ? $pk : ($defaultPk ? $defaultPk : 'id')) . '_seq';
        }
        return $defaultSeq;
    }

    /**
     * Resets the sequence of a table's primary key to the maximum value.
     *
     * @param string $tableName  A table name.
     * @param string $pk         A primary key name. Defaults to the existing
     *                           primary key.
     * @param string $sequence   A sequence name. Defaults to the sequence name
     *                           of the existing primary key.
     *
     * @return int  The (next) sequence value if a primary key and a
     *                  sequence exist.
     */
    public function resetPkSequence($table, $pk = null, $sequence = null)
    {
        if (!$pk || !$sequence) {
            list($defaultPk, $defaultSequence) = $this->pkAndSequenceFor($table);
            if (!$pk) {
                $pk = $defaultPk;
            }
            if (!$sequence) {
                $sequence = $defaultSequence;
            }
        }

        if ($pk) {
            if ($sequence) {
                $quotedSequence = $this->quoteSequenceName($sequence);
                $quotedTable = $this->quoteTableName($table);
                $quotedPk = $this->quoteColumnName($pk);
                if ($this->postgresqlVersion() >= 100000) {
                    $sql = sprintf('
                        SELECT setval(
                            %s,
                            (SELECT COALESCE(
                                MAX(%s) + (SELECT increment_by FROM pg_sequences WHERE schemaname=ANY(CURRENT_SCHEMAS(false)) AND sequencename=%s),
                                (SELECT min_value FROM pg_sequences WHERE schemaname=ANY(CURRENT_SCHEMAS(false)) AND sequencename=%s)
                             ) FROM %s),
                             false
                         )',
                         $quotedSequence,
                         $quotedPk,
                         $quotedSequence,
                         $quotedSequence,
                         $quotedTable
                    );
                } else {
                    $sql = sprintf(
                        'SELECT setval(%s, (SELECT COALESCE(MAX(%s) + (SELECT increment_by FROM %s), (SELECT min_value FROM %s)) FROM %s), false)',
                        $quotedSequence,
                        $quotedPk,
                        $sequence,
                        $sequence,
                        $quotedTable
                    );
                }
                $this->adapter->selectValue($sql, 'Reset sequence');
            } else {
                if ($this->adapter->logger) {
                    $this->adapter->logger->warn(sprintf('%s has primary key %s with no default sequence', $table, $pk));
                }
            }
        }
        return 0;
    }

    /**
     * Returns a table's primary key and the key's sequence.
     *
     * @param string $tableName  A table name.
     *
     * @return array  Array with two values: the primary key name and the key's
     *                sequence name.
     */
    public function pkAndSequenceFor($table)
    {
        // First try looking for a sequence with a dependency on the
        // given table's primary key.
        $sql = "
          SELECT attr.attname, seq.relname
          FROM pg_class      seq,
               pg_attribute  attr,
               pg_depend     dep,
               pg_namespace  name,
               pg_constraint cons
          WHERE seq.oid       = dep.objid
            AND seq.relkind   = 'S'
            AND attr.attrelid = dep.refobjid
            AND attr.attnum   = dep.refobjsubid
            AND attr.attrelid = cons.conrelid
            AND attr.attnum   = cons.conkey[1]
            AND cons.contype  = 'p'
            AND dep.refobjid  = '$table'::regclass";
        $result = $this->adapter->selectOne($sql, 'PK and serial sequence');

        if (!$result) {
            $sql = "
              SELECT c.column_name, c.ordinal_position,
                  pg_get_serial_sequence(t.table_name, c.column_name) as relname
              FROM information_schema.key_column_usage AS c
              LEFT JOIN information_schema.table_constraints AS t
                ON t.constraint_name = c.constraint_name
              WHERE t.table_name = '$table' AND t.constraint_type = 'PRIMARY KEY';";
            $result = $this->adapter->selectOne($sql, 'PK and custom sequence');
        }

        if (!$result) {
            return array(null, null);
        }

        // [primary_key, sequence]
        return array($result['attname'], $result['relname']);
    }

    /**
     * Returns the version of the connected PostgreSQL server.
     *
     * @return int  Zero padded PostgreSQL version, e.g. 80108 for 8.1.8.
     */
    public function postgresqlVersion()
    {
        if (!$this->version) {
            try {
                $this->version = $this->adapter->selectValue('SHOW server_version_num');
            } catch (Exception $e) {
                return 0;
            }
        }

        return $this->version;
    }
}
