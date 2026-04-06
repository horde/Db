<?php

declare(strict_types=1);

/**
 * MySQL integration tests for PSR-4 variant.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

namespace Horde\Db\Test\Integration\Mysql;

use Horde\Db\Adapter\Mysqli\Adapter as Mysqli;
use Horde\Db\Test\Integration\DatabaseTestCase;

/**
 * Test Horde\Db with MySQL using PSR-4 (src/) variant.
 *
 * @covers Horde\Db\Adapter\Mysqli\Adapter
 */
class Psr4AdapterTest extends DatabaseTestCase
{
    private $conn;

    protected function setUp(): void
    {
        $this->requireDatabase('mysql');

        $config = $this->getMysqlConfig();
        $this->conn = new Mysqli($config);
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            $this->dropTables($this->conn, ['test_users', 'test_table', 'test_charset']);
            $this->conn->disconnect();
        }
    }

    public function testConnection()
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

    public function testCreateTable()
    {
        $this->conn->execute('CREATE TABLE test_users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))');

        $tables = $this->conn->tables();
        $this->assertContains('test_users', $tables);
    }

    public function testInsertAndSelect()
    {
        $this->conn->execute('CREATE TABLE test_users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))');
        $id = $this->conn->insert("INSERT INTO test_users (name) VALUES ('Alice')");

        $this->assertGreaterThan(0, $id);

        $result = $this->conn->selectValue('SELECT name FROM test_users WHERE id = ?', [$id]);
        $this->assertEquals('Alice', $result);
    }

    public function testSelectAll()
    {
        $this->conn->execute('CREATE TABLE test_users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))');
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Alice')");
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Bob')");

        $result = $this->conn->selectAll('SELECT name FROM test_users ORDER BY id');
        $this->assertCount(2, $result);
        $this->assertEquals('Alice', $result[0]['name']);
        $this->assertEquals('Bob', $result[1]['name']);
    }

    public function testTransaction()
    {
        $this->conn->execute('CREATE TABLE test_users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255)) ENGINE=InnoDB');

        $this->conn->beginDbTransaction();
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Alice')");
        $this->conn->commitDbTransaction();

        $count = $this->conn->selectValue('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(1, $count);
    }

    public function testTransactionRollback()
    {
        $this->conn->execute('CREATE TABLE test_users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255)) ENGINE=InnoDB');

        $this->conn->beginDbTransaction();
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Alice')");
        $this->conn->rollbackDbTransaction();

        $count = $this->conn->selectValue('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(0, $count);
    }

    public function testSchemaOperations()
    {
        // Create table
        $this->conn->execute('CREATE TABLE test_table (id INT PRIMARY KEY AUTO_INCREMENT, value VARCHAR(255))');

        // Check columns
        $columns = $this->conn->columns('test_table');
        $this->assertCount(2, $columns);

        // Check column names
        $columnNames = array_map(fn($col) => $col->getName(), $columns);
        $this->assertContains('id', $columnNames);
        $this->assertContains('value', $columnNames);
    }

    public function testQuoting()
    {
        $quoted = $this->conn->quoteString("O'Reilly");
        $this->assertEquals("'O\\'Reilly'", $quoted);
    }

    public function testUtf8mb4Charset()
    {
        // Create table with utf8mb4 charset
        $this->conn->execute('CREATE TABLE test_charset (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(255)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        // Insert emoji (requires utf8mb4)
        $this->conn->execute("INSERT INTO test_charset (name) VALUES ('Hello 👋 World')");

        $result = $this->conn->selectValue('SELECT name FROM test_charset WHERE id = 1');
        $this->assertEquals('Hello 👋 World', $result);
    }

    public function testTextColumnWithoutDefault()
    {
        // TEXT columns cannot have default values in MySQL < 8.0.13
        // In MySQL 8.0.13+, they can have default expressions
        $this->conn->execute('CREATE TABLE test_table (
            id INT PRIMARY KEY AUTO_INCREMENT,
            content TEXT
        ) ENGINE=InnoDB');

        $columns = $this->conn->columns('test_table');
        $contentColumn = null;
        foreach ($columns as $col) {
            if ($col->getName() === 'content') {
                $contentColumn = $col;
                break;
            }
        }

        $this->assertNotNull($contentColumn);
        $this->assertEquals('text', $contentColumn->getType());
    }

    public function testPreparedStatement()
    {
        $this->conn->execute('CREATE TABLE test_users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))');

        $stmt = $this->conn->prepare('INSERT INTO test_users (name) VALUES (?)');
        $stmt->execute(['Alice']);
        $stmt->execute(['Bob']);

        $count = $this->conn->selectValue('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(2, $count);
    }

    public function testSelectAssoc()
    {
        $this->conn->execute('CREATE TABLE test_users (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(255))');
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Alice')");
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Bob')");

        $result = $this->conn->selectAssoc('SELECT id, name FROM test_users ORDER BY id');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $keys = array_keys($result);
        $this->assertEquals('Alice', $result[$keys[0]]);
        $this->assertEquals('Bob', $result[$keys[1]]);
    }
}
