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

use Horde\Test\TestCase;
use Horde\Db\DbException;
use Exception;
use LogicException;
use Horde\Db\Value\Binary as BinaryValue;
use Horde\Db\Value\Text as TextValue;

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
abstract class TestBase extends TestCase
{
    protected static $_columnTest;

    protected static $_tableTest;

    protected static $_skip = true;

    protected static $_reason;

    protected $conn;

    protected static function _getConnection($overrides = array())
    {
        throw new LogicException('_getConnection() must be implemented in a sub-class.');
    }

    protected function setUp(): void
    {
        if (self::$_skip ||
            !($res = static::_getConnection())) {
            $this->markTestSkipped(self::$_reason);
        }

        list($this->conn, $this->_cache) = $res;
        self::$_columnTest->conn = $this->conn;
        self::$_tableTest->conn = $this->conn;

        // clear out detritus from any previous test runs.
        $this->_dropTestTables();
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            // clean up
            $this->_dropTestTables();

            // close connection
            $this->conn->disconnect();
        }
    }


    /*##########################################################################
    # Connection
    ##########################################################################*/

    public function testConnect()
    {
        $this->assertTrue($this->conn->isActive());
    }

    public function testDisconnect()
    {
        $this->conn->disconnect();
        $this->assertFalse($this->conn->isActive());

        $this->conn->connect();
        $this->assertTrue($this->conn->isActive());
    }

    public function testReconnect()
    {
        $this->conn->reconnect();
        $this->assertTrue($this->conn->isActive());
    }


    /*##########################################################################
    # Accessor
    ##########################################################################*/

    abstract public function testAdapterName();

    abstract public function testSupportsMigrations();

    abstract public function testSupportsCountDistinct();

    abstract public function testSupportsInterval();


    /*##########################################################################
    # Database Statements
    ##########################################################################*/

    public function testSelect()
    {
        $this->_createTable();

        $sql = "SELECT * FROM unit_tests WHERE id='1'";
        $result = $this->conn->select($sql);
        $this->assertInstanceOf('Traversable', $result);
        $this->assertGreaterThan(0, count(iterator_to_array($result)));

        foreach ($result as $row) {
            break;
        }
        $this->assertIsArray($row);
        $this->assertEquals(1, $row['id']);
    }

    public function testSelectWithBoundParameters()
    {
        $this->_createTable();

        $sql = "SELECT * FROM unit_tests WHERE id=?";
        $result = $this->conn->select($sql, array(1));
        $this->assertInstanceOf('Traversable', $result);
        $this->assertGreaterThan(0, count(iterator_to_array($result)));

        foreach ($result as $row) {
            break;
        }
        $this->assertIsArray($row);
        $this->assertEquals(1, $row['id']);
    }

    public function testSelectWithBoundParametersQuotesString()
    {
        $this->_createTable();

        $sql = "SELECT * FROM unit_tests WHERE string_value=?";
        $result = $this->conn->select($sql, array('name a'));
        $this->assertInstanceOf('Traversable', $result);
        $this->assertGreaterThan(0, count(iterator_to_array($result)));

        foreach ($result as $row) {
            break;
        }
        $this->assertIsArray($row);
        $this->assertEquals(1, $row['id']);
    }

    public function testSelectAll()
    {
        $this->_createTable();

        $sql = "SELECT * FROM unit_tests WHERE id='1'";
        $result = $this->conn->selectAll($sql);
        $this->assertIsArray($result);
        $this->assertGreaterThan(0, count($result));
        $this->assertEquals(1, $result[0]['id']);
    }

    public function testSelectOne()
    {
        $this->_createTable();

        $sql = "SELECT * FROM unit_tests WHERE id='1'";
        $result = $this->conn->selectOne($sql);
        $this->assertArrayHasKey('id', $result);
        $this->assertEquals(1, $result['id']);
    }

    public function testSelectValue()
    {
        $this->_createTable();

        $sql = "SELECT * FROM unit_tests WHERE id='1'";
        $result = $this->conn->selectValue($sql);
        $this->assertEquals(1, $result);
    }

    public function testSelectValues()
    {
        $this->_createTable();

        $sql = "SELECT * FROM unit_tests";
        $result = $this->conn->selectValues($sql);
        $this->assertEquals(array(1, 2, 3, 4, 5, 6), $result);
    }

    public function testInsert()
    {
        $this->_createTable();

        $sql = "INSERT INTO unit_tests (id, integer_value) VALUES (7, 999)";
        $result = $this->conn->insert($sql, null, null, null, 7);

        $this->assertEquals(7, $result);
    }

    public function testInsertBlob()
    {
        $this->_createTable();
        $columns = $this->conn->columns('unit_tests');

        $result = $this->conn->insertBlob(
            'unit_tests',
            array(
                'id' => 7,
                'integer_value' => 999,
                'blob_value' => new BinaryValue(str_repeat("\0", 5000))
            ),
            null,
            7
        );
        $this->assertEquals(7, $result);
        $this->assertEquals(
            str_repeat("\0", 5000),
            $columns['blob_value']->binaryToString($this->conn->selectValue(
                'SELECT blob_value FROM unit_tests WHERE id = 7'
            ))
        );

        $result = $this->conn->insertBlob(
            'unit_tests',
            array(
                'id' => 8,
                'integer_value' => 1000,
                'text_value' => new TextValue(str_repeat('X', 5000))
            ),
            null,
            8
        );
        $this->assertEquals(8, $result);
        $this->assertEquals(
            str_repeat('X', 5000),
            $columns['text_value']->binaryToString($this->conn->selectValue(
                'SELECT text_value FROM unit_tests WHERE id = 8'
            ))
        );

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, str_repeat('X', 5000));
        $result = $this->conn->insertBlob(
            'unit_tests',
            array(
                'id' => 9,
                'integer_value' => 1001,
                'text_value' => new TextValue($stream)
            ),
            null,
            9
        );
        $this->assertEquals(9, $result);
        $this->assertEquals(
            str_repeat('X', 5000),
            $columns['text_value']->binaryToString($this->conn->selectValue(
                'SELECT text_value FROM unit_tests WHERE id = 8'
            ))
        );

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, str_repeat("\0", 10000));
        $result = $this->conn->insertBlob(
            'unit_tests',
            array(
                'id' => 10,
                'integer_value' => 1002,
                'blob_value' => new BinaryValue($stream)
            ),
            null,
            10
        );
        $this->assertEquals(10, $result);
        $this->assertEquals(
            str_repeat("\0", 10000),
            $columns['blob_value']->binaryToString($this->conn->selectValue(
                'SELECT blob_value FROM unit_tests WHERE id = 10'
            ))
        );
    }


    public function testUpdate()
    {
        $this->_createTable();

        $sql = "UPDATE unit_tests SET integer_value=999 WHERE id IN (1)";
        $result = $this->conn->update($sql);

        $this->assertEquals(1, $result);
    }

    public function testUpdateBlob()
    {
        $this->_createTable();

        $result = $this->conn->updateBlob(
            'unit_tests',
            array(
                'blob_value' => new BinaryValue(str_repeat("\0", 5000))
            ),
            'id = 1'
        );
        $this->assertEquals(1, $result);

        $result = $this->conn->updateBlob(
            'unit_tests',
            array(
                'text_value' => new TextValue(str_repeat('X', 5000))
            ),
            'id = 1'
        );
        $this->assertEquals(1, $result);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, str_repeat('X', 5001));
        $result = $this->conn->updateBlob(
            'unit_tests',
            array(
                'text_value' => new TextValue($stream)
            ),
            'id = 1'
        );
        $this->assertEquals(1, $result);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, str_repeat("\0", 5001));
        $result = $this->conn->updateBlob(
            'unit_tests',
            array(
                'blob_value' => new BinaryValue($stream)
            ),
            'id = 1'
        );
        $this->assertEquals(1, $result);
    }

    public function testDelete()
    {
        $this->_createTable();

        $sql = "DELETE FROM unit_tests WHERE id IN (1,2)";
        $result = $this->conn->delete($sql);

        $this->assertEquals(2, $result);
    }

    public function testTransactionStarted()
    {
        $this->assertFalse($this->conn->transactionStarted());
        $this->conn->beginDbTransaction();

        $this->assertTrue($this->conn->transactionStarted());
        $this->conn->commitDbTransaction();

        $this->assertFalse($this->conn->transactionStarted());
    }

    public function testTransactionCommit()
    {
        $this->_createTable();

        $this->conn->beginDbTransaction();
        $sql = "INSERT INTO unit_tests (id, integer_value) VALUES (7, 999)";
        $this->conn->insert($sql, null, null, 'id', 7);
        $this->conn->commitDbTransaction();

        // make sure it inserted
        $sql = "SELECT integer_value FROM unit_tests WHERE id='7'";
        $this->assertEquals('999', $this->conn->selectValue($sql));
    }

    public function testTransactionRollback()
    {
        $this->_createTable();

        $this->conn->beginDbTransaction();
        $sql = "INSERT INTO unit_tests (id, integer_value) VALUES (7, 999)";
        $this->conn->insert($sql, null, null, 'id', 7);
        $this->conn->rollbackDbTransaction();

        // make sure it not inserted
        $sql = "SELECT integer_value FROM unit_tests WHERE id='7'";
        $this->assertEquals(null, $this->conn->selectValue($sql));
    }


    /*##########################################################################
    # Quoting
    ##########################################################################*/

    abstract public function testQuoteNull();

    abstract public function testQuoteTrue();

    abstract public function testQuoteFalse();

    abstract public function testQuoteInteger();

    abstract public function testQuoteFloat();

    abstract public function testQuoteString();

    abstract public function testQuoteDirtyString();

    abstract public function testQuoteColumnName();

    public function testQuoteBinary()
    {
        // Test string is foo\0bar\baz'boo\'bee - should be 20 bytes long
        $original = base64_decode('Zm9vAGJhclxiYXonYm9vXCdiZWU=');

        $table = $this->conn->createTable('binary_testings');
        $table->column('data', 'binary', array('null' => false));
        $table->end();

        $this->conn->insert('INSERT INTO binary_testings (data) VALUES (?)', array(new BinaryValue($original)));
        $retrieved = $this->conn->selectValue('SELECT data FROM binary_testings');

        $columns = $this->conn->columns('binary_testings');
        $retrieved = $columns['data']->binaryToString($retrieved);

        $this->assertEquals($original, $retrieved);
    }


    /*##########################################################################
    # Schema Statements
    ##########################################################################*/

    abstract public function testNativeDatabaseTypes();

    abstract public function testTableAliasLength();

    public function testTableAliasFor()
    {
        $alias = $this->conn->tableAliasFor('my_table_name');
        $this->assertEquals('my_table_name', $alias);
    }

    public function testTables()
    {
        $this->_createTable();

        $tables = $this->conn->tables();
        $this->assertTrue(count($tables) > 0);
        $this->assertContains('unit_tests', $tables);
    }

    public function testPrimaryKey()
    {
        $this->_createTable();

        $pk = $this->conn->primaryKey('unit_tests');
        $this->assertEquals('id', (string)$pk);
        $this->assertEquals(1, count($pk->columns));
        $this->assertEquals('id', $pk->columns[0]);

        $table = $this->conn->createTable('pk_tests', array('autoincrementKey' => false));
        $table->column('foo', 'string');
        $table->column('bar', 'string');
        $table->end();
        $pk = $this->conn->primaryKey('pk_tests');
        $this->assertEmpty((string)$pk);
        $this->assertEquals(0, count($pk->columns));
        $this->conn->addPrimaryKey('pk_tests', 'foo');
        $pk = $this->conn->primaryKey('pk_tests');
        $this->assertEquals('foo', (string)$pk);
        $this->assertEquals(1, count($pk->columns));
        $this->conn->removePrimaryKey('pk_tests');
        $pk = $this->conn->primaryKey('pk_tests');
        $this->assertEmpty((string)$pk);
        $this->assertEquals(0, count($pk->columns));
        $this->conn->addPrimaryKey('pk_tests', array('foo', 'bar'));
        $pk = $this->conn->primaryKey('pk_tests');
        $this->assertEquals('foo,bar', (string)$pk);
    }

    public function testIndexes()
    {
        $this->_createTable();

        $indexes = $this->conn->indexes('unit_tests');
        $this->assertEquals(3, count($indexes));

        // sort by name so we can predict the order of indexes
        usort($indexes, function ($a, $b) {
            return strcmp($a->name, $b->name);
        });

        // multi-column index
        $col = array('integer_value', 'string_value');
        $this->assertEquals('unit_tests', $indexes[0]->table);
        $this->assertEquals('integer_string', $indexes[0]->name);
        $this->assertEquals(false, $indexes[0]->unique);
        $this->assertEquals($col, $indexes[0]->columns);

        // unique index
        $col = array('integer_value');
        $this->assertEquals('unit_tests', $indexes[1]->table);
        $this->assertEquals('integer_value', $indexes[1]->name);
        $this->assertEquals(true, $indexes[1]->unique);
        $this->assertEquals($col, $indexes[1]->columns);

        // normal index
        $col = array('string_value');
        $this->assertEquals('unit_tests', $indexes[2]->table);
        $this->assertEquals('string_value', $indexes[2]->name);
        $this->assertEquals(false, $indexes[2]->unique);
        $this->assertEquals($col, $indexes[2]->columns);
    }

    public function testColumns()
    {
        $this->_createTable();

        $columns = $this->conn->columns('unit_tests');
        $this->assertEquals(12, count($columns));

        $col = $columns['id'];
        $this->assertEquals('id', $col->getName());
        $this->assertEquals('integer', $col->getType());
        $this->assertEquals(false, $col->isNull());
        $this->assertEquals('', $col->getDefault());
        $this->assertEquals(false, $col->isText());
        $this->assertEquals(true, $col->isNumber());

        return $col;
    }

    public function testCreateTableWithSeparatePk()
    {
        $table = $this->conn->createTable('testings', array('autoincrementKey' => false));
        $table->column('foo', 'autoincrementKey');
        $table->column('bar', 'integer');
        $table->end();

        $pkColumn = $table['foo'];

        $this->conn->insert('INSERT INTO testings (bar) VALUES (1)');

        $sql = 'SELECT * FROM testings WHERE foo = 1';
        $result = $this->conn->selectAll($sql);
        $this->assertEquals(1, count($result));

        // Manually insert a primary key value.
        $this->conn->insert('INSERT INTO testings (foo, bar) VALUES (2, 1)');
        $this->conn->insert('INSERT INTO testings (bar) VALUES (1)');

        return $pkColumn;
    }

    abstract public function testChangeColumnType();

    abstract public function testChangeColumnLimit();

    abstract public function testChangeColumnPrecisionScale();

    public function testChangeColumnNull()
    {
        $this->_createTestTable('sports');
        $column = $this->_getColumn('sports', 'name');
        $this->assertTrue($column->isNull());
        $this->conn->changeColumn(
            'sports',
            'name',
            'string',
            array('null' => false)
        );
        $column = $this->_getColumn('sports', 'name');
        $this->assertFalse($column->isNull());
        $this->conn->changeColumn(
            'sports',
            'name',
            'string',
            array('null' => true)
        );
        $column = $this->_getColumn('sports', 'name');
        $this->assertTrue($column->isNull());
    }

    abstract public function testRenameColumn();

    public function testRenameColumnWithSqlReservedWord()
    {
        $this->_createTestUsersTable();

        $this->conn->renameColumn('users', 'first_name', 'other_name');
        $this->assertTrue(in_array('other_name', $this->_columnNames('users')));
    }

    public function testAddIndex()
    {
        $this->_createTestUsersTable();

        // Limit size of last_name and key columns to support Firebird index
        // limitations.
        $this->conn->addColumn(
            'users',
            'last_name',
            'string',
            array('limit' => 100)
        );
        $this->conn->addColumn(
            'users',
            'key',
            'string',
            array('limit' => 100)
        );
        $this->conn->addColumn(
            'users',
            'administrator',
            'boolean'
        );

        $this->conn->addIndex('users', 'last_name');
        $this->conn->removeIndex('users', 'last_name');

        $this->conn->addIndex('users', array('last_name', 'first_name'));
        $this->conn->removeIndex(
            'users',
            array('column' => array('last_name', 'first_name'))
        );

        $index = $this->conn->addIndex(
            'users',
            array('last_name', 'first_name')
        );
        $this->conn->removeIndex('users', array('name' => $index));

        $this->conn->addIndex('users', array('last_name', 'first_name'));
        $this->conn->removeIndex('users', 'last_name_and_first_name');

        // quoting
        $index = $this->conn->addIndex(
            'users',
            array('key'),
            array('name' => 'key_idx', 'unique' => true)
        );
        $this->conn->removeIndex(
            'users',
            array('name' => $index, 'unique' => true)
        );

        $index = $this->conn->addIndex(
            'users',
            array('last_name', 'first_name', 'administrator'),
            array('name' => 'named_admin')
        );

        $this->conn->removeIndex('users', array('name' => $index));

        $this->markTestIncomplete();
    }

    public function testAddIndexDefault()
    {
        $this->_createTestTable('sports');
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNull($index);

        $this->conn->addIndex('sports', 'is_college');

        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNotNull($index);
    }

    public function testAddIndexMultiColumn()
    {
        $this->_createTestTable('sports');
        $index = $this->_getIndex('sports', array('name', 'is_college'));
        $this->assertNull($index);

        $this->conn->addIndex('sports', array('name', 'is_college'));

        $index = $this->_getIndex('sports', array('name', 'is_college'));
        $this->assertNotNull($index);
    }

    public function testAddIndexUnique()
    {
        $this->_createTestTable('sports');
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNull($index);

        $this->conn->addIndex('sports', 'is_college', array('unique' => true));

        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNotNull($index);
        $this->assertTrue($index->unique);
    }

    public function testAddIndexName()
    {
        $this->_createTestTable('sports');
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNull($index);

        $this->conn->addIndex('sports', 'is_college', array('name' => 'sports_test'));

        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNotNull($index);
        $this->assertEquals('sports_test', $index->name);
    }

    public function testRemoveIndexSingleColumn()
    {
        $this->_createTestTable('sports');

        // add the index
        $this->conn->addIndex('sports', 'is_college');
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNotNull($index);

        // remove it again
        $this->conn->removeIndex('sports', array('column' => 'is_college'));
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNull($index);
    }

    public function testRemoveIndexMultiColumn()
    {
        $this->_createTestTable('sports');

        // add the index
        $this->conn->addIndex('sports', array('name', 'is_college'));
        $index = $this->_getIndex('sports', array('name', 'is_college'));
        $this->assertNotNull($index);

        // remove it again
        $this->conn->removeIndex('sports', array('column' => array('name', 'is_college')));
        $index = $this->_getIndex('sports', array('name', 'is_college'));
        $this->assertNull($index);
    }

    public function testRemoveIndexByName()
    {
        $this->_createTestTable('sports');

        // add the index
        $this->conn->addIndex('sports', 'is_college', array('name' => 'sports_test'));
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNotNull($index);

        // remove it again
        $this->conn->removeIndex('sports', array('name' => 'sports_test'));
        $index = $this->_getIndex('sports', 'is_college');
        $this->assertNull($index);
    }

    public function testIndexNameInvalid()
    {
        $this->expectException(DbException::class);
        $name = $this->conn->indexName('sports');
    }

    public function testIndexNameBySingleColumn()
    {
        $name = $this->conn->indexName('sports', array('column' => 'is_college'));
        $this->assertEquals('index_sports_on_is_college', $name);
    }

    public function testIndexNameByMultiColumn()
    {
        $name = $this->conn->indexName('sports', array('column' =>
                                                array('name', 'is_college')));
        $this->assertEquals('index_sports_on_name_and_is_college', $name);
    }

    public function testIndexNameByName()
    {
        $name = $this->conn->indexName('sports', array('name' => 'test'));
        $this->assertEquals('test', $name);
    }

    abstract public function testTypeToSqlTypePrimaryKey();

    abstract public function testTypeToSqlTypeString();

    abstract public function testTypeToSqlTypeText();

    abstract public function testTypeToSqlTypeBinary();

    abstract public function testTypeToSqlTypeFloat();

    abstract public function testTypeToSqlTypeDatetime();

    abstract public function testTypeToSqlTypeTimestamp();

    abstract public function testTypeToSqlInt();

    abstract public function testTypeToSqlIntLimit();

    abstract public function testTypeToSqlDecimalPrecision();

    abstract public function testTypeToSqlDecimalScale();

    abstract public function testTypeToSqlBoolean();

    abstract public function testAddColumnOptions();

    abstract public function testAddColumnOptionsDefault();

    abstract public function testAddColumnOptionsNull();

    abstract public function testAddColumnOptionsNotNull();

    public function testAddColumnNotNullWithoutDefault()
    {
        $this->expectException(DbException::class);

        $table = $this->conn->createTable('testings');
        $table->column('foo', 'string');
        $table->end();
        $this->conn->addColumn('testings', 'bar', 'string', array('null' => false, 'default' => ''));

        $this->conn->insert("INSERT INTO testings (foo, bar) VALUES ('hello', NULL)");
    }

    public function testAddColumnNotNullWithDefault()
    {
        $this->expectException(DbException::class);

        $table = $this->conn->createTable('testings');
        $table->column('foo', 'string');
        $table->end();

        $this->conn->insert("INSERT INTO testings (id, foo) VALUES ('1', 'hello')");

        $this->conn->addColumn('testings', 'bar', 'string', array('null' => false, 'default' => 'default'));

        $this->conn->insert("INSERT INTO testings (id, foo, bar) VALUES (2, 'hello', NULL)");
    }

    public function testAddRemoveSingleField()
    {
        $this->_createTestUsersTable();

        $this->assertFalse(in_array('last_name', $this->_columnNames('users')));

        $this->conn->addColumn('users', 'last_name', 'string');
        $this->assertTrue(in_array('last_name', $this->_columnNames('users')));

        $this->conn->removeColumn('users', 'last_name');
        $this->assertFalse(in_array('last_name', $this->_columnNames('users')));
    }

    public function testAddRename()
    {
        $this->_createTestUsersTable();

        $this->conn->delete('DELETE FROM users');

        $this->conn->addColumn('users', 'girlfriend', 'string');
        $this->conn->insert("INSERT INTO users (girlfriend) VALUES ('bobette')");

        $this->conn->renameColumn('users', 'girlfriend', 'exgirlfriend');

        $bob = (object)$this->conn->selectOne('SELECT * FROM users');
        $this->assertEquals('bobette', $bob->exgirlfriend);
    }

    public function testDistinct()
    {
        $result = $this->conn->distinct('test');
        $this->assertEquals('DISTINCT test', $result);
    }

    public function testAddOrderByForAssocLimiting()
    {
        $result = $this->conn->addOrderByForAssocLimiting(
            'SELECT * FROM documents ',
            array('order' => 'name DESC')
        );
        $this->assertEquals('SELECT * FROM documents ORDER BY name DESC', $result);
    }

    abstract public function testModifyDate();

    abstract public function testBuildClause();

    public function testInsertAndReadInUtf8()
    {
        list($conn, ) = static::_getConnection(array('charset' => 'utf8'));
        $table = $conn->createTable('charset_utf8');
        $table->column('text', 'string');
        $table->end();

        $input = file_get_contents(__DIR__ . '/../fixtures/charsets/utf8.txt');
        $conn->insert('INSERT INTO charset_utf8 (text) VALUES (?)', array($input));
        $output = $conn->selectValue('SELECT text FROM charset_utf8');

        $this->assertEquals($input, $output);
    }


    /*##########################################################################
    # Autoincrement Management
    ##########################################################################*/

    public function testAutoIncrementWithTypeInColumn()
    {
        $table = $this->conn->createTable('autoinc', array('autoincrementKey' => false));
        $table->column('foo', 'autoincrementKey');
        $table->column('bar', 'integer');
        $table->end();

        try {
            $this->assertEquals(1, $this->conn->insert('INSERT INTO autoinc (bar) VALUES(5)'));
        } catch (Exception $e) {
            var_dump($this->conn->getLastQuery());
            throw $e;
        }
        $this->assertEquals(2, $this->conn->insert('INSERT INTO autoinc (bar) VALUES(6)'));
        $this->assertEquals(2, $this->conn->selectValue('SELECT foo FROM autoinc WHERE bar = 6'));
    }

    /**
     * @expectedException LogicException
     * @expectedExceptionMessage foo has already been added as a primary key
     */
    public function testAutoIncrementWithTypeInTableAndColumnDefined()
    {
        $this->expectException('LogicException');
        $table = $this->conn->createTable('autoincrement', array('autoincrementKey' => 'foo'));
        $table->column('foo', 'integer');
        $table->column('bar', 'integer');
        $table->end();
    }

    public function testAutoIncrementWithTypeInTable()
    {
        $table = $this->conn->createTable('autoinc', array('autoincrementKey' => 'foo'));
        $table->column('bar', 'integer');
        $table->end();

        $this->assertEquals(1, $this->conn->insert('INSERT INTO autoinc (bar) VALUES(5)'));
        $this->assertEquals(2, $this->conn->insert('INSERT INTO autoinc (bar) VALUES(6)'));
        $this->assertEquals(2, $this->conn->selectValue('SELECT foo FROM autoinc WHERE bar = 6'));
    }

    public function testAutoIncrementWithAddColumn()
    {
        $table = $this->conn->createTable('autoinc', array('autoincrementKey' => false));
        $table->column('bar', 'integer');
        $table->end();
        $this->conn->addColumn('autoinc', 'foo', 'autoincrementKey');

        $this->assertEquals(1, $this->conn->insert('INSERT INTO autoinc (bar) VALUES(5)'));
        $this->assertEquals(2, $this->conn->insert('INSERT INTO autoinc (bar) VALUES(6)'));
        $this->assertEquals(2, $this->conn->selectValue('SELECT foo FROM autoinc WHERE bar = 6'));
    }

    public function testAutoIncrementWithChangeColumn()
    {
        $table = $this->conn->createTable('autoinc', array('autoincrementKey' => false));
        $table->column('foo', 'integer');
        $table->column('bar', 'integer');
        $table->end();
        $this->conn->changeColumn('autoinc', 'foo', 'autoincrementKey');

        $this->assertEquals(1, $this->conn->insert('INSERT INTO autoinc (bar) VALUES(5)'));
        $this->assertEquals(2, $this->conn->insert('INSERT INTO autoinc (bar) VALUES(6)'));
        $this->assertEquals(2, $this->conn->selectValue('SELECT foo FROM autoinc WHERE bar = 6'));
    }


    /*##########################################################################
    # Table cache
    ##########################################################################*/

    public function testCachedTableIndexes()
    {
        // remove any current cache.
        $this->conn->cacheWrite('tables/indexes/cache_table', '');
        $this->assertEquals('', $this->conn->cacheRead('tables/indexes/cache_table'));

        $this->_createTestTable('cache_table');
        $idxs = $this->conn->indexes('cache_table');

        $this->assertNotEquals('', $this->conn->cacheRead('tables/indexes/cache_table'));
    }

    public function testCachedTableColumns()
    {
        // remove any current cache.
        $this->conn->cacheWrite('tables/columns/cache_table', '');
        $this->assertEquals('', $this->conn->cacheRead('tables/columns/cache_table'));

        $this->_createTestTable('cache_table');
        $cols = $this->conn->columns('cache_table');

        $this->assertNotEquals('', $this->conn->cacheRead('tables/columns/cache_table'));
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    protected function _createTable()
    {
        $table = $this->conn->createTable('unit_tests');
        $table->column('integer_value', 'integer', array('limit' => 11, 'default' => 0));
        $table->column('string_value', 'string', array('limit' => 255, 'default' => ''));
        $table->column('text_value', 'text', array());
        $table->column('float_value', 'float', array('precision' => 2, 'default' => 0.0));
        $table->column('decimal_value', 'decimal', array('precision' => 2, 'scale' => 1, 'default' => 0.0));
        $table->column('datetime_value', 'datetime', array());
        $table->column('date_value', 'date', array());
        $table->column('time_value', 'time', array());
        $table->column('blob_value', 'binary', array());
        $table->column('boolean_value', 'boolean', array('default' => false));
        $table->column('email_value', 'string', array('limit' => 255, 'default' => ''));
        $table->end();
        $this->conn->addIndex('unit_tests', 'string_value', array('name' => 'string_value'));
        $this->conn->addIndex('unit_tests', 'integer_value', array('name' => 'integer_value', 'unique' => true));
        $this->conn->addIndex('unit_tests', array('integer_value', 'string_value'), array('name' => 'integer_string'));

        // read sql file for statements
        $statements = array();
        $current_stmt = '';
        $fp = fopen(__DIR__ . '/../fixtures/unit_tests.sql', 'r');
        while ($line = fgets($fp, 8192)) {
            $line = rtrim(preg_replace('/^(.*)--.*$/s', '\1', $line));
            if (!$line) {
                continue;
            }

            $current_stmt .= $line;

            if (substr($line, -1) == ';') {
                // leave off the ending ;
                $statements[] = substr($current_stmt, 0, -1);
                $current_stmt = '';
            }
        }

        // run statements
        foreach ($statements as $stmt) {
            $this->conn->insert($stmt);
        }
    }

    protected function _createTestTable($name, $options = array())
    {
        $table = $this->conn->createTable($name, $options);
        $table->column('name', 'string');
        $table->column('is_college', 'boolean');
        $table->end();
    }

    protected function _createTestUsersTable()
    {
        $table = $this->conn->createTable('users');
        $table->column('company_id', 'integer', array('limit' => 11));
        $table->column('name', 'string', array('limit' => 255, 'default' => ''));
        $table->column('first_name', 'string', array('limit' => 40, 'default' => ''));
        $table->column('approved', 'boolean', array('default' => true));
        $table->column('type', 'string', array('limit' => 255, 'default' => ''));
        $table->column('created_at', 'datetime', array());
        $table->column('created_on', 'date', array());
        $table->column('updated_at', 'datetime', array());
        $table->column('updated_on', 'date', array());
        $table->end();
    }

    /**
     * drop test tables
     */
    protected function _dropTestTables()
    {
        $tables = array(
            'autoinc',
            'binary_testings',
            'cache_table',
            /* MySQL only? */
            'charset_cp1257',
            /* MySQL only? */
            'charset_utf8',
            'dates',
            'my_sports',
            'octopi',
            'pk_tests',
            'schema_info',
            'sports',
            'testings',
            'text_to_binary',
            'unit_tests',
            'users',
        );

        foreach ($tables as $table) {
            try {
                $this->conn->dropTable($table);
            } catch (Exception $e) {
            }
        }
    }

    protected function _columnNames($tableName)
    {
        $columns = array();
        foreach ($this->conn->columns($tableName) as $c) {
            $columns[] = $c->getName();
        }
        return $columns;
    }

    /**
     * Get a column by name
     */
    protected function _getColumn($table, $column)
    {
        foreach ($this->conn->columns($table) as $col) {
            if ($col->getName() == $column) {
                return $col;
            }
        }
    }

    /**
     * Get an index by columns
     */
    protected function _getIndex($table, $indexes)
    {
        $indexes = (array) $indexes;
        sort($indexes);

        foreach ($this->conn->indexes($table) as $index) {
            $columns = $index->columns;
            sort($columns);
            if ($columns == $indexes) {
                return $index;
            }
        }
    }

    public function testColumnConstruct()
    {
        self::$_columnTest->testConstruct();
        $this->markTestIncomplete();
    }

    public function testColumnToSql()
    {
        self::$_columnTest->testToSql();
        $this->markTestIncomplete();
    }

    public function testColumnToSqlLimit()
    {
        self::$_columnTest->testToSqlLimit();
        $this->markTestIncomplete();
    }

    public function testColumnToSqlPrecisionScale()
    {
        self::$_columnTest->testToSqlPrecisionScale();
        $this->markTestIncomplete();
    }

    public function testColumnToSqlNotNull()
    {
        self::$_columnTest->testToSqlNotNull();
        $this->markTestIncomplete();
    }

    public function testColumnToSqlDefault()
    {
        self::$_columnTest->testToSqlDefault();
        $this->markTestIncomplete();
    }

    public function testTableConstruct()
    {
        self::$_tableTest->testConstruct();
        $this->markTestIncomplete();
    }

    public function testTableName()
    {
        self::$_tableTest->testName();
        $this->markTestIncomplete();
    }

    public function testTableGetOptions()
    {
        self::$_tableTest->testGetOptions();
        $this->markTestIncomplete();
    }

    public function testTablePrimaryKey()
    {
        self::$_tableTest->testPrimaryKey();
        $this->markTestIncomplete();
    }

    public function testTableColumn()
    {
        self::$_tableTest->testColumn();
        $this->markTestIncomplete();
    }

    public function testTableToSql()
    {
        self::$_tableTest->testToSql();
        $this->markTestIncomplete();
    }
}
