<?php
/**
 * Copyright 2007 Maintainable Software, LLC
 * Copyright 2008-2017 Horde LLC (http://www.horde.org/)
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @license    http://www.horde.org/licenses/bsd
 * @category   Horde
 * @package    Db
 * @subpackage UnitTests
 */

namespace Horde\Db\Test\Adapter\Pdo;

use Horde\Db\Test\Adapter\TestBase;
use PDO;
use Horde\Db\Adapter\Pdo\Sqlite;
use Horde_Cache;
use Horde_Cache_Storage_Mock;
use Horde\Db\Test\Adapter\Sqlite\ColumnDefinition;
use Horde\Db\Test\Adapter\Sqlite\TestTableDefinition;
use Horde\Db\Adapter\Sqlite\Column;
use Exception;

/**
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @license    http://www.horde.org/licenses/bsd
 * @group      horde_db
 * @category   Horde
 * @package    Db
 * @subpackage UnitTests
 */
class SqliteTest extends TestBase
{
    public static function setUpBeforeClass(): void
    {
        self::$_reason = 'The sqlite adapter is not available';
        if (extension_loaded('pdo') &&
            in_array('sqlite', PDO::getAvailableDrivers())) {
            self::$_skip = false;
            list($conn, ) = static::_getConnection();
            $conn->disconnect();
        }
        self::$_columnTest = new ColumnDefinition();
        self::$_tableTest = new TestTableDefinition();
    }

    protected static function _getConnection($overrides = array())
    {
        $config = array(
            'dbname' => ':memory:',
        );
        $config = array_merge($config, $overrides);
        $conn = new Sqlite($config);

        $cache = new Horde_Cache(new Horde_Cache_Storage_Mock());
        $conn->setCache($cache);
        //$conn->setLogger(new Horde_Log_Logger(new Horde_Log_Handler_Cli()));

        return array($conn, $cache);
    }


    /*##########################################################################
    # Accessor
    ##########################################################################*/

    public function testAdapterName()
    {
        $this->assertEquals('PDO_SQLite', $this->conn->adapterName());
    }

    public function testSupportsMigrations()
    {
        $this->assertTrue($this->conn->supportsMigrations());
    }

    public function testSupportsCountDistinct()
    {
        $version = $this->conn->selectValue('SELECT sqlite_version(*)');
        if ($version >= '3.2.6') {
            $this->assertTrue($this->conn->supportsCountDistinct());
        } else {
            $this->assertFalse($this->conn->supportsCountDistinct());
        }
    }

    public function testSupportsInterval()
    {
        $this->assertFalse($this->conn->supportsInterval());
    }

    /*##########################################################################
    # Quoting
    ##########################################################################*/

    public function testQuoteNull()
    {
        $this->assertEquals('NULL', $this->conn->quote(null));
    }

    public function testQuoteTrue()
    {
        $this->assertEquals('1', $this->conn->quote(true));
    }

    public function testQuoteFalse()
    {
        $this->assertEquals('0', $this->conn->quote(false));
    }

    public function testQuoteInteger()
    {
        $this->assertEquals('42', $this->conn->quote(42));
    }

    public function testQuoteFloat()
    {
        $this->assertEquals('42.200000', $this->conn->quote(42.200000));
        setlocale(LC_NUMERIC, 'de_DE.UTF-8');
        $this->assertEquals('42.200000', $this->conn->quote(42.200000));
    }

    public function testQuoteString()
    {
        $this->assertEquals("'my string'", $this->conn->quote('my string'));
    }

    public function testQuoteDirtyString()
    {
        $this->assertEquals("'derek''s string'", $this->conn->quote('derek\'s string'));
    }

    public function testQuoteColumnName()
    {
        $col = new Column('age', 'NULL', 'int(11)');
        $this->assertEquals('1', $this->conn->quote(true, $col));
    }


    /*##########################################################################
    # Schema Statements
    ##########################################################################*/

    public function testNativeDatabaseTypes()
    {
        $types = $this->conn->nativeDatabaseTypes();
        $this->assertEquals(array('name' => 'int', 'limit' => null), $types['integer']);
    }

    public function testTableAliasLength()
    {
        $len = $this->conn->tableAliasLength();
        $this->assertEquals(255, $len);
    }

    public function testColumns()
    {
        $col = parent::testColumns();
        $this->assertEquals(null, $col->getLimit());
        $this->assertEquals('INTEGER', $col->getSqlType());
    }

    public function testCreateTableWithSeparatePk()
    {
        $pkColumn = parent::testCreateTableWithSeparatePk();
        $this->assertEquals('"foo" INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL', $pkColumn->toSql());
    }

    public function testChangeColumnType()
    {
        $this->_createTestTable('sports');
        $beforeChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('boolean', $beforeChange->getSqlType());

        $this->conn->changeColumn('sports', 'is_college', 'string');

        $afterChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('varchar(255)', $afterChange->getSqlType());

        $table = $this->conn->createTable('text_to_binary');
        $table->column('data', 'text');
        $table->end();
        $this->conn->insert(
            'INSERT INTO text_to_binary (data) VALUES (?)',
            array("foo")
        );

        $this->conn->changeColumn('text_to_binary', 'data', 'binary');

        $afterChange = $this->_getColumn('text_to_binary', 'data');
        $this->assertEquals('blob', $afterChange->getSqlType());
        $this->assertEquals(
            "foo",
            $this->conn->selectValue('SELECT data FROM text_to_binary')
        );
    }

    public function testChangeColumnLimit()
    {
        $this->_createTestTable('sports');
        $beforeChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('boolean', $beforeChange->getSqlType());

        $this->conn->changeColumn(
            'sports',
            'is_college',
            'string',
            array('limit' => '40')
        );

        $afterChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('varchar(40)', $afterChange->getSqlType());
    }

    public function testChangeColumnPrecisionScale()
    {
        $this->_createTestTable('sports');
        $beforeChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('boolean', $beforeChange->getSqlType());

        $this->conn->changeColumn(
            'sports',
            'is_college',
            'decimal',
            array('precision' => '5', 'scale' => '2')
        );

        $afterChange = $this->_getColumn('sports', 'is_college');
        $this->assertMatchesRegularExpression('/^decimal\(5,\s*2\)$/', $afterChange->getSqlType());
    }

    public function testRenameColumn()
    {
        $this->_createTestUsersTable();

        $this->conn->renameColumn('users', 'first_name', 'nick_name');
        $this->assertTrue(in_array('nick_name', $this->_columnNames('users')));

        $this->_createTestTable('sports');

        $beforeChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('boolean', $beforeChange->getSqlType());

        $this->conn->renameColumn('sports', 'is_college', 'is_renamed');

        $afterChange = $this->_getColumn('sports', 'is_renamed');
        $this->assertEquals('boolean', $afterChange->getSqlType());
    }

    public function testTypeToSqlTypePrimaryKey()
    {
        $result = $this->conn->typeToSql('autoincrementKey');
        $this->assertEquals('INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL', $result);
    }

    public function testTypeToSqlTypeString()
    {
        $result = $this->conn->typeToSql('string');
        $this->assertEquals('varchar(255)', $result);
    }

    public function testTypeToSqlTypeText()
    {
        $result = $this->conn->typeToSql('text');
        $this->assertEquals('text', $result);
    }

    public function testTypeToSqlTypeBinary()
    {
        $result = $this->conn->typeToSql('binary');
        $this->assertEquals('blob', $result);
    }

    public function testTypeToSqlTypeFloat()
    {
        $result = $this->conn->typeToSql('float');
        $this->assertEquals('float', $result);
    }

    public function testTypeToSqlTypeDatetime()
    {
        $result = $this->conn->typeToSql('datetime');
        $this->assertEquals('datetime', $result);
    }

    public function testTypeToSqlTypeTimestamp()
    {
        $result = $this->conn->typeToSql('timestamp');
        $this->assertEquals('datetime', $result);
    }

    public function testTypeToSqlInt()
    {
        $result = $this->conn->typeToSql('integer');
        $this->assertEquals('int', $result);
    }

    public function testTypeToSqlIntLimit()
    {
        $result = $this->conn->typeToSql('integer', '1');
        $this->assertEquals('int(1)', $result);
    }

    public function testTypeToSqlDecimalPrecision()
    {
        $result = $this->conn->typeToSql('decimal', null, '5');
        $this->assertEquals('decimal(5)', $result);
    }

    public function testTypeToSqlDecimalScale()
    {
        $result = $this->conn->typeToSql('decimal', null, '5', '2');
        $this->assertEquals('decimal(5, 2)', $result);
    }

    public function testTypeToSqlBoolean()
    {
        $result = $this->conn->typeToSql('boolean');
        $this->assertEquals('boolean', $result);
    }

    public function testAddColumnOptions()
    {
        $result = $this->conn->addColumnOptions('test', array());
        $this->assertEquals('test', $result);
    }

    public function testAddColumnOptionsDefault()
    {
        $options = array('default' => '0');
        $result = $this->conn->addColumnOptions('test', $options);
        $this->assertEquals("test DEFAULT '0'", $result);
    }

    public function testAddColumnOptionsNull()
    {
        $options = array('null' => true);
        $result = $this->conn->addColumnOptions('test', $options);
        $this->assertEquals('test', $result);
    }

    public function testAddColumnOptionsNotNull()
    {
        $options = array('null' => false);
        $result = $this->conn->addColumnOptions('test', $options);
        $this->assertEquals('test NOT NULL', $result);
    }

    public function testModifyDate()
    {
        $modifiedDate = $this->conn->modifyDate('start', '+', 1, 'DAY');
        $this->assertEquals('datetime(start, \'+1 days\')', $modifiedDate);

        $t = $this->conn->createTable('dates');
        $t->column('start', 'datetime');
        $t->column('end', 'datetime');
        $t->end();
        $this->conn->insert(
            'INSERT INTO dates (start, end) VALUES (?, ?)',
            array(
                '2011-12-10 00:00:00',
                '2011-12-11 00:00:00'
            )
        );
        $this->assertEquals(
            1,
            $this->conn->selectValue('SELECT COUNT(*) FROM dates WHERE '
                                      . $modifiedDate . ' = end')
        );

        $this->assertEquals(
            'datetime(start, \'+2 seconds\')',
            $this->conn->modifyDate('start', '+', 2, 'SECOND')
        );
        $this->assertEquals(
            'datetime(start, \'+3 minutes\')',
            $this->conn->modifyDate('start', '+', 3, 'MINUTE')
        );
        $this->assertEquals(
            'datetime(start, \'+4 hours\')',
            $this->conn->modifyDate('start', '+', 4, 'HOUR')
        );
        $this->assertEquals(
            'datetime(start, \'-2 months\')',
            $this->conn->modifyDate('start', '-', 2, 'MONTH')
        );
        $this->assertEquals(
            'datetime(start, \'-3 years\')',
            $this->conn->modifyDate('start', '-', 3, 'YEAR')
        );
    }

    public function testBuildClause()
    {
        $this->assertEquals(
            'bitmap & 2',
            $this->conn->buildClause('bitmap', '&', 2)
        );
        $this->assertEquals(
            array('bitmap & ?', array(2)),
            $this->conn->buildClause('bitmap', '&', 2, true)
        );

        $this->assertEquals(
            'bitmap | 2',
            $this->conn->buildClause('bitmap', '|', 2)
        );
        $this->assertEquals(
            array('bitmap | ?', array(2)),
            $this->conn->buildClause('bitmap', '|', 2, true)
        );

        $this->assertEquals(
            "LOWER(name) LIKE LOWER('%search%')",
            $this->conn->buildClause('name', 'LIKE', "search")
        );
        $this->assertEquals(
            array("LOWER(name) LIKE LOWER(?)", array('%search%')),
            $this->conn->buildClause('name', 'LIKE', "search", true)
        );
        $this->assertEquals(
            "LOWER(name) LIKE LOWER('%search\&replace\?%')",
            $this->conn->buildClause('name', 'LIKE', "search&replace?")
        );
        $this->assertEquals(
            array("LOWER(name) LIKE LOWER(?)", array('%search&replace?%')),
            $this->conn->buildClause('name', 'LIKE', "search&replace?", true)
        );
        $this->assertEquals(
            "(LOWER(name) LIKE LOWER('search\&replace\?%') OR LOWER(name) LIKE LOWER('% search\&replace\?%'))",
            $this->conn->buildClause('name', 'LIKE', "search&replace?", false, array('begin' => true))
        );
        $this->assertEquals(
            array("(LOWER(name) LIKE LOWER(?) OR LOWER(name) LIKE LOWER(?))",
                  array('search&replace?%', '% search&replace?%')),
            $this->conn->buildClause('name', 'LIKE', "search&replace?", true, array('begin' => true))
        );

        $this->assertEquals(
            'value = 2',
            $this->conn->buildClause('value', '=', 2)
        );
        $this->assertEquals(
            array('value = ?', array(2)),
            $this->conn->buildClause('value', '=', 2, true)
        );
        $this->assertEquals(
            "value = 'foo'",
            $this->conn->buildClause('value', '=', 'foo')
        );
        $this->assertEquals(
            array('value = ?', array('foo')),
            $this->conn->buildClause('value', '=', 'foo', true)
        );
        $this->assertEquals(
            "value = 'foo\?bar'",
            $this->conn->buildClause('value', '=', 'foo?bar')
        );
        $this->assertEquals(
            array('value = ?', array('foo?bar')),
            $this->conn->buildClause('value', '=', 'foo?bar', true)
        );
    }

    public function testInsertAndReadInCp1257()
    {
        list($conn, ) = static::_getConnection(array('charset' => 'cp1257'));
        $table = $conn->createTable('charset_cp1257');
        $table->column('text', 'string');
        $table->end();

        $input = file_get_contents(__DIR__ . '/../../fixtures/charsets/cp1257.txt');
        $conn->insert('INSERT INTO charset_cp1257 (text) VALUES (?)', array($input));
        $output = $conn->selectValue('SELECT text FROM charset_cp1257');

        $this->assertEquals($input, $output);
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    /**
     * Create table to perform tests on
     */
    protected function _createTestTable($name, $options = array())
    {
        parent::_createTestTable($name, $options = array());
        try {
            // make sure table was created
            $sql = "INSERT INTO $name
                    VALUES (1, 'mlb', 0)";
            $this->conn->insert($sql);
        } catch (Exception $e) {
        }
    }
}
