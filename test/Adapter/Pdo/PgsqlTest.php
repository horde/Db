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

use PDO;
use Horde\Db\Test\Adapter\TestBase;
use Horde\Db\Test\Adapter\Postgresql\ColumnDefinition;
use Horde\Db\Test\Adapter\Postgresql\TestTableDefinition;
use Horde\Db\Adapter\Postgresql\Column;
use Horde\Test\TestCase;
use Horde\Db\Adapter\Pdo\Pgsql;
use Horde_Cache;
use Horde_Cache_Storage_Mock;
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
class PgsqlTest extends TestBase
{
    public static function setUpBeforeClass(): void
    {
        self::$_reason = 'The pgsql adapter is not available';
        if (extension_loaded('pdo') &&
            in_array('pgsql', PDO::getAvailableDrivers())) {
            self::$_skip = false;
            list($conn, ) = static::_getConnection();
            if ($conn) {
                $conn->disconnect();
            }
        }
        self::$_columnTest = new ColumnDefinition();
        self::$_tableTest = new TestTableDefinition();
    }

    protected static function _getConnection($overrides = array())
    {
        $config = TestCase::getConfig(
            'DB_ADAPTER_PDO_PGSQL_TEST_CONFIG',
            __DIR__ . '/..',
            array('username' => '',
                                                   'password' => '',
                                                   'dbname' => 'test')
        );
        if (isset($config['db']['adapter']['pdo']['pgsql']['test'])) {
            $config = $config['db']['adapter']['pdo']['pgsql']['test'];
        }
        if (!is_array($config)) {
            self::$_skip = true;
            self::$_reason = 'No configuration for pdo_pgsql test';
            return;
        }
        $config = array_merge($config, $overrides);

        $conn = new Pgsql($config);

        $cache = new Horde_Cache(new Horde_Cache_Storage_Mock());
        $conn->setCache($cache);

        return array($conn, $cache);
    }


    /*##########################################################################
    # Accessor
    ##########################################################################*/

    public function testAdapterName()
    {
        $this->assertEquals('PDO_PostgreSQL', $this->conn->adapterName());
    }

    public function testSupportsMigrations()
    {
        $this->assertTrue($this->conn->supportsMigrations());
    }

    public function testSupportsCountDistinct()
    {
        $this->assertTrue($this->conn->supportsCountDistinct());
    }

    public function testSupportsInterval()
    {
        $this->assertTrue($this->conn->supportsInterval());
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
        $this->assertEquals("'t'", $this->conn->quote(true));
    }

    public function testQuoteFalse()
    {
        $this->assertEquals("'f'", $this->conn->quote(false));
    }

    public function testQuoteInteger()
    {
        $this->assertEquals('42', $this->conn->quote(42));
    }

    public function testQuoteFloat()
    {
        $this->assertEquals('42.2', $this->conn->quote(42.2));
        setlocale(LC_NUMERIC, 'de_DE.UTF-8');
        $this->assertEquals('42.2', $this->conn->quote(42.2));
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
        $this->assertEquals(array('name' => 'integer', 'limit' => null), $types['integer']);
    }

    public function testTableAliasLength()
    {
        $len = $this->conn->tableAliasLength();
        $this->assertGreaterThanOrEqual(63, $len);
    }

    public function testColumns()
    {
        $col = parent::testColumns();
        $this->assertEquals(null, $col->getLimit());
        $this->assertEquals('integer', $col->getSqlType());
    }

    public function testCreateTableWithSeparatePk()
    {
        $pkColumn = parent::testCreateTableWithSeparatePk();
        $this->assertEquals('"foo" serial primary key', $pkColumn->toSql());
    }

    public function testChangeColumnType()
    {
        $this->_createTestTable('sports');
        $beforeChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('boolean', $beforeChange->getSqlType());

        $this->conn->changeColumn('sports', 'is_college', 'string');

        $afterChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('character varying(255)', $afterChange->getSqlType());

        $table = $this->conn->createTable('text_to_binary');
        $table->column('data', 'text');
        $table->end();
        $this->conn->insert(
            'INSERT INTO text_to_binary (data) VALUES (?)',
            array("foo")
        );

        $this->conn->changeColumn('text_to_binary', 'data', 'binary');

        $afterChange = $this->_getColumn('text_to_binary', 'data');
        $this->assertEquals('bytea', $afterChange->getSqlType());
        $this->assertEquals(
            "foo",
            stream_get_contents($this->conn->selectValue('SELECT data FROM text_to_binary'))
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
        $this->assertEquals('character varying(40)', $afterChange->getSqlType());
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
        $this->assertEquals('numeric(5,2)', $afterChange->getSqlType());
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
        $this->assertEquals('serial primary key', $result);
    }

    public function testTypeToSqlTypeString()
    {
        $result = $this->conn->typeToSql('string');
        $this->assertEquals('character varying(255)', $result);
    }

    public function testTypeToSqlTypeText()
    {
        $result = $this->conn->typeToSql('text');
        $this->assertEquals('text', $result);
    }

    public function testTypeToSqlTypeBinary()
    {
        $result = $this->conn->typeToSql('binary');
        $this->assertEquals('bytea', $result);
    }

    public function testTypeToSqlTypeFloat()
    {
        $result = $this->conn->typeToSql('float');
        $this->assertEquals('float', $result);
    }

    public function testTypeToSqlTypeDatetime()
    {
        $result = $this->conn->typeToSql('datetime');
        $this->assertEquals('timestamp', $result);
    }

    public function testTypeToSqlTypeTimestamp()
    {
        $result = $this->conn->typeToSql('timestamp');
        $this->assertEquals('timestamp', $result);
    }

    public function testTypeToSqlInt()
    {
        $result = $this->conn->typeToSql('integer');
        $this->assertEquals('integer', $result);
    }

    public function testTypeToSqlIntLimit()
    {
        $result = $this->conn->typeToSql('integer', '1');
        $this->assertEquals('smallint', $result);
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
        $result = $this->conn->addColumnOptions("test", array());
        $this->assertEquals("test", $result);
    }

    public function testAddColumnOptionsDefault()
    {
        $options = array('default' => '0');
        $result = $this->conn->addColumnOptions("test", $options);
        $this->assertEquals("test DEFAULT '0'", $result);
    }

    public function testAddColumnOptionsNull()
    {
        $options = array('null' => true);
        $result = $this->conn->addColumnOptions("test", $options);
        $this->assertEquals("test", $result);
    }

    public function testAddColumnOptionsNotNull()
    {
        $options = array('null' => false);
        $result = $this->conn->addColumnOptions("test", $options);
        $this->assertEquals("test NOT NULL", $result);
    }

    public function testInterval()
    {
        $this->assertEquals(
            'INTERVAL \'1 DAY \'',
            $this->conn->interval('1 DAY', '')
        );
    }

    public function testModifyDate()
    {
        $modifiedDate = $this->conn->modifyDate('mystart', '+', 1, 'DAY');
        $this->assertEquals('mystart + INTERVAL \'1 DAY\'', $modifiedDate);

        $t = $this->conn->createTable('dates');
        $t->column('mystart', 'datetime');
        $t->column('myend', 'datetime');
        $t->end();
        $this->conn->insert(
            'INSERT INTO dates (mystart, myend) VALUES (?, ?)',
            array(
                '2011-12-10 00:00:00',
                '2011-12-11 00:00:00'
            )
        );
        $this->assertEquals(
            1,
            $this->conn->selectValue('SELECT COUNT(*) FROM dates WHERE '
                                      . $modifiedDate . ' = myend')
        );
    }

    public function testBuildClause()
    {
        $this->assertEquals(
            "CASE WHEN CAST(bitmap AS VARCHAR) ~ '^-?[0-9]+$' THEN (CAST(bitmap AS INTEGER) & 2) ELSE 0 END",
            $this->conn->buildClause('bitmap', '&', 2)
        );
        $this->assertEquals(
            array("CASE WHEN CAST(bitmap AS VARCHAR) ~ '^-?[0-9]+$' THEN (CAST(bitmap AS INTEGER) & ?) ELSE 0 END", array(2)),
            $this->conn->buildClause('bitmap', '&', 2, true)
        );

        $this->assertEquals(
            "CASE WHEN CAST(bitmap AS VARCHAR) ~ '^-?[0-9]+$' THEN (CAST(bitmap AS INTEGER) | 2) ELSE 0 END",
            $this->conn->buildClause('bitmap', '|', 2)
        );
        $this->assertEquals(
            array("CASE WHEN CAST(bitmap AS VARCHAR) ~ '^-?[0-9]+$' THEN (CAST(bitmap AS INTEGER) | ?) ELSE 0 END", array(2)),
            $this->conn->buildClause('bitmap', '|', 2, true)
        );

        $this->assertEquals(
            "name ILIKE '%search%'",
            $this->conn->buildClause('name', 'LIKE', "search")
        );
        $this->assertEquals(
            array("name ILIKE ?", array('%search%')),
            $this->conn->buildClause('name', 'LIKE', "search", true)
        );
        $this->assertEquals(
            "name ILIKE '%search\&replace\?%'",
            $this->conn->buildClause('name', 'LIKE', "search&replace?")
        );
        $this->assertEquals(
            array("name ILIKE ?", array('%search&replace?%')),
            $this->conn->buildClause('name', 'LIKE', "search&replace?", true)
        );
        $this->assertEquals(
            "(name ILIKE 'search\&replace\?%' OR name ILIKE '% search\&replace\?%')",
            $this->conn->buildClause('name', 'LIKE', "search&replace?", false, array('begin' => true))
        );
        $this->assertEquals(
            array("(name ILIKE ? OR name ILIKE ?)",
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
                    VALUES (1, 'mlb', 'f')";
            $this->conn->insert($sql);
        } catch (Exception $e) {
        }
    }
}
