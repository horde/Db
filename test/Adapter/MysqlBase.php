<?php
/**
 * Copyright 2007 Maintainable Software, LLC
 * Copyright 2008-2017 Horde LLC (http://www.horde.org/)
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/bsd
 * @category   Horde
 * @package    Db
 * @subpackage UnitTests
 */

namespace Horde\Db\Test\Adapter;

use LogicException;
use Exception;
use Horde_String;
use Horde\Db\DbException;
use Horde\Db\Adapter\Mysql\Column;
use Horde\Db\Test\Adapter\Mysql\ColumnDefinition;
use Horde\Db\Test\Adapter\Mysql\TestTableDefinition;

/**
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/bsd
 * @group      horde_db
 * @category   Horde
 * @package    Db
 * @subpackage UnitTests
 */
abstract class MysqlBase extends TestBase
{
    protected static function _available()
    {
        throw new LogicException('_available() must be implemented in a sub-class.');
    }

    public static function setUpBeforeClass(): void
    {
        self::$_reason = 'The MySQL adapter is not available';
        if (static::_available()) {
            self::$_skip = false;
            list($conn, ) = static::_getConnection();
            if (self::$_skip) {
                return;
            }
            $conn->disconnect();
        }
        self::$_columnTest = new ColumnDefinition();
        self::$_tableTest = new TestTableDefinition();
    }


    /*##########################################################################
    # Accessor
    ##########################################################################*/

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

    public function testGetCharset()
    {
        $this->assertEquals('utf8', Horde_String::lower($this->conn->getCharset()));
    }


    /*##########################################################################
    # Database Statements
    ##########################################################################*/

    public function testTransactionCommit()
    {
        parent::testTransactionCommit();

        // query without transaction and with new connection (see bug #10578).
        $sql = "INSERT INTO unit_tests (id, integer_value) VALUES (8, 1000)";
        $this->conn->insert($sql);

        // make sure it inserted
        $this->conn->reconnect();
        $sql = "SELECT integer_value FROM unit_tests WHERE id='8'";
        $this->assertEquals('1000', $this->conn->selectValue($sql));
    }

    public function testTransactionRollback()
    {
        parent::testTransactionRollback();

        // query without transaction and with new connection (see bug #10578).
        $sql = "INSERT INTO unit_tests (id, integer_value) VALUES (7, 999)";
        $this->conn->insert($sql, null, null, 'id', 7);

        // make sure it inserted
        $this->conn->reconnect();
        $sql = "SELECT integer_value FROM unit_tests WHERE id='7'";
        $this->assertEquals(999, $this->conn->selectValue($sql));
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
        $this->assertEquals("'derek\'s string'", $this->conn->quote('derek\'s string'));
    }

    public function testQuoteColumnName()
    {
        $col = new Column('age', 'NULL', 'int(11)');
        $this->assertEquals('1', $this->conn->quote(true, $col));
    }


    /*##########################################################################
    # Schema Statements
    ##########################################################################*/

    /**
     * We specifically do a manual INSERT here, and then test only the SELECT
     * functionality. This allows us to more easily catch INSERT being broken,
     * but SELECT actually working fine.
     */
    public function testNativeDecimalInsertManualVsAutomatic()
    {
        $this->_createTestUsersTable();

        $correctValue = 12345678901234567890.0123456789;

        $this->conn->addColumn("users", "wealth", 'decimal', array('precision' => 30, 'scale' => 10));

        // do a manual insertion
        $this->conn->execute("INSERT INTO users (wealth) VALUES ('12345678901234567890.0123456789')");

        // SELECT @todo - type cast attribute values
        $user = (object)$this->conn->selectOne('SELECT * FROM users');
        // assert_kind_of BigDecimal, row.wealth

        // If this assert fails, that means the SELECT is broken!
        $this->assertEquals($correctValue, $user->wealth);

        // Reset to old state
        $this->conn->delete('DELETE FROM users');

        // Now use the Adapter insertion
        $this->conn->insert('INSERT INTO users (wealth) VALUES (12345678901234567890.0123456789)');

        // SELECT @todo - type cast attribute values
        $user = (object)$this->conn->selectOne('SELECT * FROM users');
        // assert_kind_of BigDecimal, row.wealth

        // If these asserts fail, that means the INSERT (create function, or cast to SQL) is broken!
        $this->assertEquals($correctValue, $user->wealth);
    }

    public function testNativeTypes()
    {
        $this->_createTestUsersTable();

        $this->conn->addColumn("users", "last_name", 'string');
        $this->conn->addColumn("users", "bio", 'text');
        $this->conn->addColumn("users", "age", 'integer');
        $this->conn->addColumn("users", "height", 'float');
        $this->conn->addColumn("users", "wealth", 'decimal', array('precision' => '30', 'scale' => '10'));
        $this->conn->addColumn("users", "birthday", 'datetime');
        $this->conn->addColumn("users", "favorite_day", 'date');
        $this->conn->addColumn("users", "moment_of_truth", 'datetime');
        $this->conn->addColumn("users", "male", 'boolean');

        $this->conn->insert('INSERT INTO users (first_name, last_name, bio, age, height, wealth, birthday, favorite_day, moment_of_truth, male, company_id) ' .
                             "VALUES ('bob', 'bobsen', 'I was born ....', 18, 1.78, 12345678901234567890.0123456789, '2005-01-01 12:23:40', '1980-03-05', '1582-10-10 21:40:18', 1, 1)");

        $bob = (object)$this->conn->selectOne('SELECT * FROM users');
        $this->assertEquals('bob', $bob->first_name);
        $this->assertEquals('bobsen', $bob->last_name);
        $this->assertEquals('I was born ....', $bob->bio);
        $this->assertEquals(18, $bob->age);

        // Test for 30 significent digits (beyond the 16 of float), 10 of them
        // after the decimal place.
        $this->assertEquals('12345678901234567890.0123456789', $bob->wealth);
        $this->assertEquals(1, $bob->male);

        // @todo - type casting
    }

    public function testNativeDatabaseTypes()
    {
        $types = $this->conn->nativeDatabaseTypes();
        $this->assertEquals(array('name' => 'int', 'limit' => 11), $types['integer']);
    }

    public function testUnabstractedDatabaseDependentTypes()
    {
        $this->_createTestUsersTable();
        $this->conn->delete('DELETE FROM users');

        $this->conn->addColumn('users', 'intelligence_quotient', 'tinyint');
        try {
            $this->conn->insert('INSERT INTO users (intelligence_quotient) VALUES (300)');
            $jonnyg = (object)$this->conn->selectOne('SELECT * FROM users');
            $this->assertEquals('127', $jonnyg->intelligence_quotient);
        } catch (DbException $e) {
            if (strpos($e->getMessage(), "Out of range value for column 'intelligence_quotient' at row 1") === false) {
                throw $e;
            }
        }
    }

    public function testTableAliasLength()
    {
        $len = $this->conn->tableAliasLength();
        $this->assertEquals(255, $len);
    }

    public function testColumns()
    {
        $col = parent::testColumns();
        $this->assertEquals(10, $col->getLimit());
        $this->assertEquals(true, $col->isUnsigned());
        $this->assertEquals('int(10) unsigned', $col->getSqlType());
    }

    public function testCreateTableWithSeparatePk()
    {
        $pkColumn = parent::testCreateTableWithSeparatePk();
        $this->assertEquals('`foo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $pkColumn->toSql());
    }

    public function testChangeColumnType()
    {
        $this->_createTestTable('sports');
        $beforeChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('tinyint(1)', $beforeChange->getSqlType());

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
        $this->assertEquals('longblob', $afterChange->getSqlType());
        $this->assertEquals(
            "foo",
            $this->conn->selectValue('SELECT data FROM text_to_binary')
        );
    }

    public function testChangeColumnLimit()
    {
        $this->_createTestTable('sports');
        $beforeChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('tinyint(1)', $beforeChange->getSqlType());

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
        $this->assertEquals('tinyint(1)', $beforeChange->getSqlType());

        $this->conn->changeColumn(
            'sports',
            'is_college',
            'decimal',
            array('precision' => '5', 'scale' => '2')
        );

        $afterChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('decimal(5,2)', $afterChange->getSqlType());
    }

    public function testChangeColumnUnsigned()
    {
        $table = $this->conn->createTable('testings');
        $table->column('foo', 'integer');
        $table->end();

        $beforeChange = $this->_getColumn('testings', 'foo');
        $this->assertFalse($beforeChange->isUnsigned());

        $this->conn->execute('INSERT INTO testings (id, foo) VALUES (1, -1)');

        try {
            $this->conn->changeColumn('testings', 'foo', 'integer', array('unsigned' => true));

            $afterChange = $this->_getColumn('testings', 'foo');
            $this->assertTrue($afterChange->isUnsigned());

            $row = (object)$this->conn->selectOne('SELECT * FROM testings');
            $this->assertEquals(0, $row->foo);
        } catch (DbException $e) {
            if (strpos($e->getMessage(), "Out of range value for column 'foo' at row 1") === false) {
                throw $e;
            }
        }
    }

    public function testRenameColumn()
    {
        $this->_createTestUsersTable();

        $this->conn->renameColumn('users', 'first_name', 'nick_name');
        $this->assertTrue(in_array('nick_name', $this->_columnNames('users')));

        $this->_createTestTable('sports');

        $beforeChange = $this->_getColumn('sports', 'is_college');
        $this->assertEquals('tinyint(1)', $beforeChange->getSqlType());

        $this->conn->renameColumn('sports', 'is_college', 'is_renamed');

        $afterChange = $this->_getColumn('sports', 'is_renamed');
        $this->assertEquals('tinyint(1)', $afterChange->getSqlType());
    }

    public function testTypeToSqlTypePrimaryKey()
    {
        $result = $this->conn->typeToSql('autoincrementKey');
        $this->assertEquals('int(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $result);
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
        $this->assertEquals('longblob', $result);
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
        $this->assertEquals('int(11)', $result);
    }

    public function testTypeToSqlIntUnsigned()
    {
        $result = $this->conn->typeToSql('integer', null, null, null, true);
        $this->assertEquals('int(10) UNSIGNED', $result);
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
        $this->assertEquals('tinyint(1)', $result);
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
            'INTERVAL  1 DAY',
            $this->conn->interval('1 DAY', '')
        );
    }

    public function testModifyDate()
    {
        $modifiedDate = $this->conn->modifyDate('start', '+', 1, 'DAY');
        $this->assertEquals('start + INTERVAL \'1\' DAY', $modifiedDate);

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

        $input = file_get_contents(__DIR__ . '/../fixtures/charsets/cp1257.txt');
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

    public function testColumnToSqlUnsigned()
    {
        self::$_columnTest->testToSqlUnsigned();
    }
}
