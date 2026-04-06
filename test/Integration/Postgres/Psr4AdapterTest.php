<?php

declare(strict_types=1);

/**
 * PostgreSQL integration tests for PSR-4 variant.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

namespace Horde\Db\Test\Integration\Postgres;

use Horde\Db\Adapter\Pdo\Postgresql;
use Horde\Db\Test\Integration\DatabaseTestCase;

/**
 * Test Horde\Db with PostgreSQL using PSR-4 (src/) variant.
 *
 * @covers Horde\Db\Adapter\Pdo\Postgresql
 */
class Psr4AdapterTest extends DatabaseTestCase
{
    private $conn;

    protected function setUp(): void
    {
        $this->requireDatabase('postgres');

        $config = $this->getPostgresConfig();
        $this->conn = new Postgresql($config);
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            $this->dropTables($this->conn, ['test_users', 'test_table', 'test_json']);
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
        $this->conn->execute('CREATE TABLE test_users (id SERIAL PRIMARY KEY, name VARCHAR(255))');

        $tables = $this->conn->tables();
        $this->assertContains('test_users', $tables);
    }

    public function testInsertAndSelect()
    {
        $this->conn->execute('CREATE TABLE test_users (id SERIAL PRIMARY KEY, name VARCHAR(255))');
        $id = $this->conn->insert("INSERT INTO test_users (name) VALUES ('Alice') RETURNING id");

        $this->assertGreaterThan(0, $id);

        $result = $this->conn->selectValue('SELECT name FROM test_users WHERE id = $1', [$id]);
        $this->assertEquals('Alice', $result);
    }

    public function testSelectAll()
    {
        $this->conn->execute('CREATE TABLE test_users (id SERIAL PRIMARY KEY, name VARCHAR(255))');
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Alice')");
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Bob')");

        $result = $this->conn->selectAll('SELECT name FROM test_users ORDER BY id');
        $this->assertCount(2, $result);
        $this->assertEquals('Alice', $result[0]['name']);
        $this->assertEquals('Bob', $result[1]['name']);
    }

    public function testTransaction()
    {
        $this->conn->execute('CREATE TABLE test_users (id SERIAL PRIMARY KEY, name VARCHAR(255))');

        $this->conn->beginDbTransaction();
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Alice')");
        $this->conn->commitDbTransaction();

        $count = $this->conn->selectValue('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(1, $count);
    }

    public function testTransactionRollback()
    {
        $this->conn->execute('CREATE TABLE test_users (id SERIAL PRIMARY KEY, name VARCHAR(255))');

        $this->conn->beginDbTransaction();
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Alice')");
        $this->conn->rollbackDbTransaction();

        $count = $this->conn->selectValue('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(0, $count);
    }

    public function testSchemaOperations()
    {
        // Create table
        $this->conn->execute('CREATE TABLE test_table (id SERIAL PRIMARY KEY, value VARCHAR(255))');

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

    public function testJsonColumn()
    {
        $this->conn->execute('CREATE TABLE test_json (
            id SERIAL PRIMARY KEY,
            data JSON
        )');

        $this->conn->execute("INSERT INTO test_json (data) VALUES ('{\"name\": \"Alice\", \"age\": 30}')");

        $result = $this->conn->selectValue('SELECT data FROM test_json WHERE id = 1');
        $this->assertIsString($result);
        $decoded = json_decode($result, true);
        $this->assertEquals('Alice', $decoded['name']);
        $this->assertEquals(30, $decoded['age']);
    }

    public function testTextColumnWithDefault()
    {
        // PostgreSQL allows TEXT columns to have default values
        $this->conn->execute('CREATE TABLE test_table (
            id SERIAL PRIMARY KEY,
            content TEXT DEFAULT \'default content\'
        )');

        $this->conn->execute('INSERT INTO test_table DEFAULT VALUES');

        $result = $this->conn->selectValue('SELECT content FROM test_table WHERE id = 1');
        $this->assertEquals('default content', $result);
    }

    public function testPreparedStatement()
    {
        $this->conn->execute('CREATE TABLE test_users (id SERIAL PRIMARY KEY, name VARCHAR(255))');

        $stmt = $this->conn->prepare('INSERT INTO test_users (name) VALUES ($1)');
        $stmt->execute(['Alice']);
        $stmt->execute(['Bob']);

        $count = $this->conn->selectValue('SELECT COUNT(*) FROM test_users');
        $this->assertEquals(2, $count);
    }

    public function testSelectAssoc()
    {
        $this->conn->execute('CREATE TABLE test_users (id SERIAL PRIMARY KEY, name VARCHAR(255))');
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Alice')");
        $this->conn->execute("INSERT INTO test_users (name) VALUES ('Bob')");

        $result = $this->conn->selectAssoc('SELECT id, name FROM test_users ORDER BY id');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $keys = array_keys($result);
        $this->assertEquals('Alice', $result[$keys[0]]);
        $this->assertEquals('Bob', $result[$keys[1]]);
    }

    public function testBooleanColumn()
    {
        $this->conn->execute('CREATE TABLE test_table (
            id SERIAL PRIMARY KEY,
            active BOOLEAN DEFAULT TRUE
        )');

        $this->conn->execute('INSERT INTO test_table DEFAULT VALUES');
        $this->conn->execute('INSERT INTO test_table (active) VALUES (FALSE)');

        $results = $this->conn->selectAll('SELECT active FROM test_table ORDER BY id');
        $this->assertTrue($results[0]['active'] === true || $results[0]['active'] === 't');
        $this->assertTrue($results[1]['active'] === false || $results[1]['active'] === 'f');
    }
}
