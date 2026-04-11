<?php

declare(strict_types=1);

/**
 * Oracle integration tests for PSR-4 variant.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

namespace Horde\Db\Test\Integration\Oracle;

use Horde\Db\Adapter\Oracle\Adapter as Oracle;
use Horde\Db\Test\Integration\DatabaseTestCase;
use Exception;

/**
 * Test Horde\Db with Oracle using PSR-4 (src/) variant.
 *
 * @covers Horde\Db\Adapter\Oracle\Adapter
 */
class Psr4AdapterTest extends DatabaseTestCase
{
    private $conn;

    protected function setUp(): void
    {
        $this->requireDatabase('oracle');

        $config = $this->getOracleConfig();
        $this->conn = new Oracle($config);
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            $this->dropTables($this->conn, ['test_users', 'test_table']);
            // Drop sequences
            try {
                $this->conn->execute('DROP SEQUENCE test_users_seq');
            } catch (Exception $e) {
                // Ignore if doesn't exist
            }
            $this->conn->disconnect();
        }
    }

    public function testConnection()
    {
        // Lazy connect: not active until first query
        $this->assertFalse($this->conn->isActive());
        $this->conn->selectValue('SELECT 1 FROM DUAL');
        $this->assertTrue($this->conn->isActive());
    }

    public function testDisconnect()
    {
        $this->conn->disconnect();
        $this->assertFalse($this->conn->isActive());

        $this->conn->connect();
        $this->assertTrue($this->conn->isActive());
    }

    public function testCreateTable()
    {
        $this->conn->execute('CREATE TABLE test_users (
            id NUMBER PRIMARY KEY,
            name VARCHAR2(255)
        )');

        $tables = $this->conn->tables();
        $this->assertContains('test_users', array_map('strtolower', $tables));
    }

    public function testInsertAndSelect()
    {
        $this->conn->execute('CREATE SEQUENCE test_users_seq START WITH 1');
        $this->conn->execute('CREATE TABLE test_users (
            id NUMBER PRIMARY KEY,
            name VARCHAR2(255)
        )');

        $this->conn->execute("INSERT INTO test_users (id, name) VALUES (test_users_seq.NEXTVAL, 'Alice')");

        $result = $this->conn->selectValue('SELECT name FROM test_users WHERE id = 1');
        $this->assertEquals('Alice', $result);
    }

    public function testSelectAll()
    {
        $this->conn->execute('CREATE SEQUENCE test_users_seq START WITH 1');
        $this->conn->execute('CREATE TABLE test_users (
            id NUMBER PRIMARY KEY,
            name VARCHAR2(255)
        )');

        $this->conn->execute("INSERT INTO test_users (id, name) VALUES (test_users_seq.NEXTVAL, 'Alice')");
        $this->conn->execute("INSERT INTO test_users (id, name) VALUES (test_users_seq.NEXTVAL, 'Bob')");

        $result = $this->conn->selectAll('SELECT name FROM test_users ORDER BY id');
        $this->assertCount(2, $result);
        $this->assertEquals('Alice', $result[0]['name']);
        $this->assertEquals('Bob', $result[1]['name']);
    }

    public function testTransaction()
    {
        $this->conn->execute('CREATE TABLE test_users (
            id NUMBER PRIMARY KEY,
            name VARCHAR2(255)
        )');

        $this->conn->beginDbTransaction();
        $this->conn->execute("INSERT INTO test_users (id, name) VALUES (1, 'Alice')");
        $this->conn->commitDbTransaction();

        $count = $this->conn->selectValue('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(1, $count);
    }

    public function testTransactionRollback()
    {
        $this->conn->execute('CREATE TABLE test_users (
            id NUMBER PRIMARY KEY,
            name VARCHAR2(255)
        )');

        $this->conn->beginDbTransaction();
        $this->conn->execute("INSERT INTO test_users (id, name) VALUES (1, 'Alice')");
        $this->conn->rollbackDbTransaction();

        $count = $this->conn->selectValue('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(0, $count);
    }

    public function testSchemaOperations()
    {
        // Create table
        $this->conn->execute('CREATE TABLE test_table (
            id NUMBER PRIMARY KEY,
            value VARCHAR2(255)
        )');

        // Check columns
        $columns = $this->conn->columns('test_table');
        $this->assertCount(2, $columns);

        // Check column names (Oracle returns uppercase)
        $columnNames = array_map(fn($col) => strtolower($col->getName()), $columns);
        $this->assertContains('id', $columnNames);
        $this->assertContains('value', $columnNames);
    }

    public function testQuoting()
    {
        $quoted = $this->conn->quoteString("O'Reilly");
        $this->assertEquals("'O''Reilly'", $quoted);
    }

    public function testClobColumn()
    {
        $this->conn->execute('CREATE TABLE test_table (
            id NUMBER PRIMARY KEY,
            content CLOB
        )');

        $longText = str_repeat('Lorem ipsum ', 1000);
        $stmt = $this->conn->prepare('INSERT INTO test_table (id, content) VALUES (1, :content)');
        $stmt->execute(['content' => $longText]);

        $result = $this->conn->selectValue('SELECT content FROM test_table WHERE id = 1');
        $this->assertEquals($longText, $result);
    }

    public function testPreparedStatement()
    {
        $this->conn->execute('CREATE TABLE test_users (
            id NUMBER PRIMARY KEY,
            name VARCHAR2(255)
        )');

        $stmt = $this->conn->prepare('INSERT INTO test_users (id, name) VALUES (:id, :name)');
        $stmt->execute(['id' => 1, 'name' => 'Alice']);
        $stmt->execute(['id' => 2, 'name' => 'Bob']);

        $count = $this->conn->selectValue('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(2, $count);
    }

    public function testSelectAssoc()
    {
        $this->conn->execute('CREATE TABLE test_users (
            id NUMBER PRIMARY KEY,
            name VARCHAR2(255)
        )');

        $this->conn->execute("INSERT INTO test_users (id, name) VALUES (1, 'Alice')");
        $this->conn->execute("INSERT INTO test_users (id, name) VALUES (2, 'Bob')");

        $result = $this->conn->selectAssoc('SELECT id, name FROM test_users ORDER BY id');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Alice', $result[1]);
        $this->assertEquals('Bob', $result[2]);
    }

    public function testDateColumn()
    {
        $this->conn->execute('CREATE TABLE test_table (
            id NUMBER PRIMARY KEY,
            created_at DATE
        )');

        $this->conn->execute("INSERT INTO test_table (id, created_at) VALUES (1, TO_DATE('2024-01-15', 'YYYY-MM-DD'))");

        $result = $this->conn->selectValue('SELECT created_at FROM test_table WHERE id = 1');
        $this->assertNotEmpty($result);
    }
}
