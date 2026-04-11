<?php

declare(strict_types=1);

/**
 * SQLite integration tests for PSR-4 variant.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

namespace Horde\Db\Test\Integration\Sqlite;

use Horde\Db\Adapter\Pdo\Sqlite;
use Horde\Db\Test\Integration\DatabaseTestCase;

/**
 * Test Horde\Db with SQLite using PSR-4 (src/) variant.
 *
 * @covers Horde\Db\Adapter\Pdo\Sqlite
 */
class Psr4AdapterTest extends DatabaseTestCase
{
    private $conn;

    protected function setUp(): void
    {
        $config = $this->getSqliteConfig();
        $this->conn = new Sqlite($config);
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            $this->conn->disconnect();
        }
    }

    public function testConnection()
    {
        // Lazy connect: not active until first query
        $this->assertFalse($this->conn->isActive());
        $this->conn->selectValue('SELECT 1');
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
        $this->conn->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT)');

        $tables = $this->conn->tables();
        $this->assertContains('test_users', $tables);
    }

    public function testInsertAndSelect()
    {
        $this->conn->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT)');
        $this->conn->insert("INSERT INTO test_users (name) VALUES ('Alice')");

        $result = $this->conn->selectValue('SELECT name FROM test_users WHERE id = 1');
        $this->assertEquals('Alice', $result);
    }

    public function testSelectAll()
    {
        $this->conn->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT)');
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Alice')");
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Bob')");

        $result = $this->conn->selectAll('SELECT name FROM test_users ORDER BY id');
        $this->assertCount(2, $result);
        $this->assertEquals('Alice', $result[0]['name']);
        $this->assertEquals('Bob', $result[1]['name']);
    }

    public function testTransaction()
    {
        $this->conn->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT)');

        $this->conn->beginDbTransaction();
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Alice')");
        $this->conn->commitDbTransaction();

        $count = $this->conn->selectValue('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(1, $count);
    }

    public function testTransactionRollback()
    {
        $this->conn->execute('CREATE TABLE test_users (id INTEGER PRIMARY KEY, name TEXT)');

        $this->conn->beginDbTransaction();
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Alice')");
        $this->conn->rollbackDbTransaction();

        $count = $this->conn->selectValue('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(0, $count);
    }

    public function testSchemaOperations()
    {
        // Create table
        $this->conn->execute('CREATE TABLE test_table (id INTEGER PRIMARY KEY, value TEXT)');

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
        $this->assertEquals("'O''Reilly'", $quoted);
    }
}
