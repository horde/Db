<?php

/**
 * Copyright 2007-2026 Maintainable Software, LLC
 * Copyright 2006-2026 Horde LLC (http://www.horde.org/)
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

/**
 * Base class for Horde_Db migrations.
 *
 * Migration files subclass this and override {@see up()} and {@see down()},
 * calling schema-DDL methods (createTable, dropTable, addIndex, addColumn,
 * renameColumn, etc.) plus lower-level adapter methods (execute, select,
 * insert, etc.) directly on `$this`. Those calls are dispatched by
 * {@see __call()} to the underlying {@see Horde_Db_Adapter}, which in
 * turn proxies schema calls to its {@see Horde_Db_Adapter_Base_Schema}.
 *
 * Two-level dynamic dispatch defeats static analysis. The `@method`
 * annotations below mirror the public API of both proxied surfaces so
 * PHPStan (and any other static analyzer) can see through the proxy
 * without needing per-migration `@method` tags or a fresh baseline in
 * every consuming package.
 *
 * The list intentionally covers everything a real migration might reach
 * for. Signatures match {@see Horde_Db_Adapter_Base_Schema} and
 * {@see Horde_Db_Adapter} verbatim; runtime behavior is unaffected.
 *
 * Schema DDL (from Horde_Db_Adapter_Base_Schema):
 * @method array nativeDatabaseTypes()
 * @method int tableAliasLength()
 * @method string tableAliasFor(string $tableName)
 * @method array tables()
 * @method Horde_Db_Adapter_Base_TableDefinition|Horde_Db_Adapter_Base_Table table(string $tableName, ?string $name = null)
 * @method mixed primaryKey(string $tableName, ?string $name = null)
 * @method array indexes(string $tableName, ?string $name = null)
 * @method array columns(string $tableName, ?string $name = null)
 * @method Horde_Db_Adapter_Base_Column column(string $tableName, string $columnName)
 * @method mixed createTable(string $name, array $options = [])
 * @method mixed endTable(string $name, array $options = [])
 * @method mixed renameTable(string $name, string $newName)
 * @method mixed dropTable(string $name)
 * @method mixed addColumn(string $tableName, string $columnName, string $type, array $options = [])
 * @method mixed removeColumn(string $tableName, string $columnName)
 * @method mixed changeColumn(string $tableName, string $columnName, string $type, array $options = [])
 * @method mixed changeColumnDefault(string $tableName, string $columnName, $default)
 * @method mixed renameColumn(string $tableName, string $columnName, string $newColumnName)
 * @method mixed addPrimaryKey(string $tableName, mixed $columns)
 * @method mixed removePrimaryKey(string $tableName)
 * @method mixed addIndex(string $tableName, mixed $columnName, array $options = [])
 * @method mixed removeIndex(string $tableName, array $options = [])
 * @method string indexName(string $tableName, array $options = [])
 * @method mixed recreateDatabase(string $name)
 * @method mixed createDatabase(string $name, array $options = [])
 * @method mixed dropDatabase(string $name)
 * @method string currentDatabase()
 * @method string typeToSql(string $type, ?int $limit = null, ?int $precision = null, ?int $scale = null, ?bool $unsigned = null)
 * @method string addColumnOptions(string $sql, array $options, string $sqlType = '')
 * @method string distinct(mixed $columns, ?string $orderBy = null)
 * @method string addOrderByForAssocLimiting(string $sql, array $options)
 * @method string interval(string $interval, string $precision)
 * @method string modifyDate(mixed $reference, string $operator, mixed $amount, string $interval)
 * @method string buildClause(string $lhs, string $op, mixed $rhs, bool $bind = false, array $params = [])
 * @method Horde_Db_Adapter_Base_ColumnDefinition makeColumn(string $name, mixed $default, ?string $sqlType = null, bool $null = true)
 * @method Horde_Db_Adapter_Base_ColumnDefinition makeColumnDefinition(mixed $base, string $name, string $type, ?int $limit = null, ?int $precision = null, ?int $scale = null, bool $unsigned = false, mixed $default = null, bool $null = true, mixed $autoincrement = false)
 * @method Horde_Db_Adapter_Base_Index makeIndex(string $table, string $name, bool $primary, bool $unique, array $columns)
 * @method Horde_Db_Adapter_Base_Table makeTable(string $name, mixed $primaryKey, array $columns, array $indexes)
 * @method Horde_Db_Adapter_Base_TableDefinition makeTableDefinition(string $name, mixed $base, array $options = [])
 *
 * Quoting (from Horde_Db_Adapter_Base_Schema):
 * @method string quote(mixed $value, ?Horde_Db_Adapter_Base_Column $column = null)
 * @method string quoteString(string $string)
 * @method string quoteColumnName(string $name)
 * @method string quoteTableName(string $name)
 * @method string quoteTrue()
 * @method string quoteFalse()
 * @method string quoteDate(mixed $value)
 * @method string quoteBinary(mixed $value)
 *
 * Lower-level adapter methods (from Horde_Db_Adapter and Adapter_Base):
 * @method string adapterName()
 * @method bool supportsMigrations()
 * @method bool supportsCountDistinct()
 * @method bool supportsInterval()
 * @method mixed prefetchPrimaryKey(?string $tableName = null)
 * @method mixed connect()
 * @method bool isActive()
 * @method mixed reconnect()
 * @method mixed disconnect()
 * @method mixed rawConnection()
 * @method PDOStatement|Horde_Db_Adapter_Base_Result select(string $sql, mixed $arg1 = null, mixed $arg2 = null)
 * @method array selectAll(string $sql, mixed $arg1 = null, mixed $arg2 = null)
 * @method array|false selectOne(string $sql, mixed $arg1 = null, mixed $arg2 = null)
 * @method mixed selectValue(string $sql, mixed $arg1 = null, mixed $arg2 = null)
 * @method array selectValues(string $sql, mixed $arg1 = null, mixed $arg2 = null)
 * @method array selectAssoc(string $sql, mixed $arg1 = null, mixed $arg2 = null)
 * @method mixed execute(string $sql, mixed $arg1 = null, mixed $arg2 = null)
 * @method mixed insert(string $sql, mixed $arg1 = null, mixed $arg2 = null, ?string $pk = null, mixed $idValue = null, ?string $sequenceName = null)
 * @method mixed insertBlob(string $table, array $fields, ?string $pk = null, mixed $idValue = null)
 * @method int update(string $sql, mixed $arg1 = null, mixed $arg2 = null)
 * @method mixed updateBlob(string $table, array $fields, string $where = '')
 * @method int delete(string $sql, mixed $arg1 = null, mixed $arg2 = null)
 * @method bool transactionStarted()
 * @method mixed beginDbTransaction()
 * @method mixed commitDbTransaction()
 * @method mixed rollbackDbTransaction()
 * @method string addLimitOffset(string $sql, array $options)
 * @method mixed addLock(string &$sql, array $options = [])
 * @method mixed insertFixture(array $fixture, string $tableName)
 * @method string emptyInsertStatement(string $tableName)
 * @method string getLastQuery()
 * @method mixed resetRuntime()
 * @method mixed cacheWrite(string $key, mixed $value)
 * @method mixed cacheRead(string $key)
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @category   Horde
 * @copyright  2007 Maintainable Software, LLC
 * @copyright  2006-2017 Horde LLC
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Migration
 */
class Horde_Db_Migration_Base
{
    /**
     * The migration version
     * @var integer
     */
    public $version = null;

    /**
     * The logger
     * @var Horde_Log_Logger
     */
    protected $_logger;

    /**
     * Database connection adapter
     * @var Horde_Db_Adapter_Base
     */
    protected $_connection;


    /*##########################################################################
    # Constructor
    ##########################################################################*/

    /**
     */
    public function __construct(Horde_Db_Adapter $connection, $version = null)
    {
        $this->_connection = $connection;
        $this->version = $version;
    }


    /*##########################################################################
    # Public
    ##########################################################################*/

    /**
     * Proxy methods over to the connection
     * @param   string  $method
     * @param   array   $args
     */
    public function __call($method, $args)
    {
        $a = [];
        foreach ($args as $arg) {
            if (is_array($arg)) {
                $vals = [];
                foreach ($arg as $key => $value) {
                    $vals[] = var_export($key, true) . ' => ' . var_export($value, true);
                }
                $a[] = 'array(' . implode(', ', $vals) . ')';
            } else {
                $a[] = var_export($arg, true);
            }
        }
        $this->say("$method(" . implode(", ", $a) . ")");

        // benchmark method call
        $t = new Horde_Support_Timer();
        $t->push();
        $result = call_user_func_array([$this->_connection, $method], $args);
        $time = $t->pop();

        // print stats
        $this->say(sprintf("%.4fs", $time), 'subitem');
        if (is_int($result)) {
            $this->say("$result rows", 'subitem');
        }

        return $result;
    }

    public function upWithBechmarks()
    {
        $this->migrate('up');
    }

    public function downWithBenchmarks()
    {
        $this->migrate('down');
    }

    /**
     * Execute this migration in the named direction
     */
    public function migrate($direction)
    {
        if (!method_exists($this, $direction)) {
            return;
        }

        if ($direction == 'up') {
            $this->announce("migrating");
        }
        if ($direction == 'down') {
            $this->announce("reverting");
        }

        $result = null;
        $t = new Horde_Support_Timer();
        $t->push();
        $result = $this->$direction();
        $time = $t->pop();

        if ($direction == 'up') {
            $this->announce("migrated (" . sprintf("%.4fs", $time) . ")");
            $this->log();
        }
        if ($direction == 'down') {
            $this->announce("reverted (" . sprintf("%.4fs", $time) . ")");
            $this->log();
        }
        return $result;
    }

    /**
     * @param   string  $text
     */
    public function log($text = '')
    {
        if ($this->_logger) {
            $this->_logger->info($text);
        }
    }

    /**
     * @param Horde_Log_Logger $logger
     */
    public function setLogger($logger)
    {
        $this->_logger = $logger;
    }

    /**
     * Announce migration
     * @param   string  $message
     */
    public function announce($message)
    {
        $text = "$this->version " . get_class($this) . ": $message";
        $length = 75 - strlen($text) > 0 ? 75 - strlen($text) : 0;

        $this->log(sprintf("== %s %s", $text, str_repeat('=', $length)));
    }

    /**
     * @param   string  $message
     * @param   boolean $subitem
     */
    public function say($message, $subitem = false)
    {
        $this->log(($subitem ? "   ->" : "--") . " $message");
    }
}
