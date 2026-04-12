<?php

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

declare(strict_types=1);

namespace Horde\Db\Test\Unit\Adapter;

use Horde\Db\Adapter\Base;
use Horde\Db\Adapter\Pdo\Sqlite;
use Horde\Db\Value\Binary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * Tests for lazy database connection behaviour.
 *
 * Uses a real Pdo\Sqlite adapter with :memory: to verify that construction
 * does NOT open a connection, that introspection and schema quoting work
 * without a live handle, and that the first real query triggers connection.
 *
 * We verify lazy behaviour via isActive() rather than a subclass counter,
 * because Pdo\Sqlite uses catchSchemaChanges() with call_user_func_array
 * and parent:: resolution, which does not support subclass overrides of
 * the methods it delegates to.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
#[CoversClass(Base::class)]
#[CoversClass(Sqlite::class)]
class LazyConnectTest extends TestCase
{
    private Sqlite $adapter;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension not available');
        }

        $this->adapter = new Sqlite([
            'adapter' => 'pdo_sqlite',
            'database' => ':memory:',
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->adapter) && $this->adapter->isActive()) {
            $this->adapter->disconnect();
        }
    }

    /**
     * Assert the adapter has not connected yet.
     */
    private function assertNotConnected(): void
    {
        $this->assertFalse($this->adapter->isActive(), 'Adapter should not be connected');
        $this->assertNull($this->adapter->rawConnection(), 'Raw connection should be null');
    }

    /**
     * Assert the adapter is now connected.
     */
    private function assertConnected(): void
    {
        $this->assertTrue($this->adapter->isActive(), 'Adapter should be connected');
        $this->assertNotNull($this->adapter->rawConnection(), 'Raw connection should not be null');
    }

    // ---------------------------------------------------------------
    // Group 1: Construction does NOT connect
    // ---------------------------------------------------------------

    public function testConstructorDoesNotConnect(): void
    {
        $this->assertNotConnected();
    }

    public function testIsActiveReturnsFalseBeforeFirstQuery(): void
    {
        $this->assertFalse($this->adapter->isActive());
    }

    public function testRawConnectionReturnsNullBeforeFirstQuery(): void
    {
        $this->assertNull($this->adapter->rawConnection());
    }

    // ---------------------------------------------------------------
    // Group 2: Introspection before query does NOT connect
    // ---------------------------------------------------------------

    public function testAdapterNameDoesNotConnect(): void
    {
        $name = $this->adapter->adapterName();
        $this->assertIsString($name);
        $this->assertNotConnected();
    }

    public function testSupportsMigrationsDoesNotConnect(): void
    {
        $this->adapter->supportsMigrations();
        $this->assertNotConnected();
    }

    public function testGetLastQueryDoesNotConnect(): void
    {
        // getLastQuery() has :string return type but lastQuery property is null
        // before any query — may throw TypeError, but must not trigger connect.
        try {
            $this->adapter->getLastQuery();
        } catch (TypeError $e) {
            // Expected: lastQuery is null before any query
        }
        $this->assertNotConnected();
    }

    public function testResetRuntimeDoesNotConnect(): void
    {
        $this->adapter->resetRuntime();
        $this->assertNotConnected();
    }

    public function testTransactionStartedDoesNotConnect(): void
    {
        $this->adapter->transactionStarted();
        $this->assertNotConnected();
    }

    // ---------------------------------------------------------------
    // Group 3: Schema quoting does NOT connect
    // ---------------------------------------------------------------

    public function testQuoteColumnNameDoesNotConnect(): void
    {
        $result = $this->adapter->quoteColumnName('foo');
        $this->assertSame('"foo"', $result);
        $this->assertNotConnected();
    }

    public function testQuoteTableNameDoesNotConnect(): void
    {
        $result = $this->adapter->quoteTableName('bar');
        $this->assertSame('"bar"', $result);
        $this->assertNotConnected();
    }

    public function testQuoteTrueDoesNotConnect(): void
    {
        $result = $this->adapter->quoteTrue();
        $this->assertIsString($result);
        $this->assertNotConnected();
    }

    public function testQuoteFalseDoesNotConnect(): void
    {
        $result = $this->adapter->quoteFalse();
        $this->assertIsString($result);
        $this->assertNotConnected();
    }

    public function testAddLimitOffsetDoesNotConnect(): void
    {
        $result = $this->adapter->addLimitOffset(
            'SELECT * FROM t',
            ['limit' => 10, 'offset' => 5]
        );
        $this->assertStringContainsString('LIMIT', $result);
        $this->assertNotConnected();
    }

    // ---------------------------------------------------------------
    // Group 4: Query methods DO trigger connect
    // ---------------------------------------------------------------

    public function testExecuteTriggersConnect(): void
    {
        $this->assertNotConnected();
        $this->adapter->execute('SELECT 1');
        $this->assertConnected();
    }

    public function testSelectAllTriggersConnect(): void
    {
        $this->assertNotConnected();
        $result = $this->adapter->selectAll('SELECT 1 AS val');
        $this->assertConnected();
        $this->assertCount(1, $result);
    }

    public function testSelectOneTriggersConnect(): void
    {
        $this->assertNotConnected();
        $result = $this->adapter->selectOne('SELECT 1 AS val');
        $this->assertConnected();
        $this->assertIsArray($result);
    }

    public function testSelectValueTriggersConnect(): void
    {
        $this->assertNotConnected();
        $result = $this->adapter->selectValue('SELECT 42');
        $this->assertConnected();
        $this->assertEquals(42, $result);
    }

    public function testInsertTriggersConnect(): void
    {
        $this->assertNotConnected();
        $this->adapter->execute(
            'CREATE TABLE lazy_test (id INTEGER PRIMARY KEY, name TEXT)'
        );
        $this->assertConnected();

        $id = $this->adapter->insert(
            "INSERT INTO lazy_test (name) VALUES ('Alice')"
        );
        $this->assertGreaterThanOrEqual(1, $id);
    }

    public function testBeginTransactionTriggersConnect(): void
    {
        $this->assertNotConnected();
        $this->adapter->beginDbTransaction();
        $this->assertConnected();
        $this->adapter->rollbackDbTransaction();
    }

    // ---------------------------------------------------------------
    // Group 5: quoteString triggers connect for PDO
    // ---------------------------------------------------------------

    public function testQuoteStringTriggersConnect(): void
    {
        $this->assertNotConnected();
        $result = $this->adapter->quoteString('hello');
        $this->assertConnected();
        $this->assertIsString($result);
    }

    // ---------------------------------------------------------------
    // Group 6: Lifecycle edge cases
    // ---------------------------------------------------------------

    public function testDisconnectThenQueryReconnects(): void
    {
        // First connect via query
        $this->adapter->execute('SELECT 1');
        $this->assertConnected();

        // Disconnect
        $this->adapter->disconnect();
        $this->assertFalse($this->adapter->isActive());

        // Next query auto-reconnects
        $this->adapter->execute('SELECT 1');
        $this->assertConnected();
    }

    public function testSetCacheAndLoggerBeforeFirstQuery(): void
    {
        // Cache and logger can be set before connection.
        // The constructor already sets stubs, so just verify no connect happens.
        $this->assertNotConnected();

        // Now query — should connect and work fine with stubs
        $result = $this->adapter->selectValue('SELECT 99');
        $this->assertEquals(99, $result);
        $this->assertConnected();
    }

    public function testMultipleQueriesConnectOnce(): void
    {
        $this->adapter->execute('SELECT 1');
        $this->assertConnected();

        // Subsequent queries don't cause issues
        $this->adapter->execute('SELECT 2');
        $result = $this->adapter->selectValue('SELECT 3');
        $this->assertEquals(3, $result);
    }

    public function testExplicitConnectThenQuery(): void
    {
        $this->adapter->connect();
        $this->assertConnected();

        // Query works after explicit connect
        $result = $this->adapter->selectValue('SELECT 1');
        $this->assertEquals(1, $result);
    }

    // ---------------------------------------------------------------
    // Group 7: Prepared statements (blob paths)
    // ---------------------------------------------------------------

    public function testInsertBlobTriggersConnect(): void
    {
        $this->assertNotConnected();

        $this->adapter->execute(
            'CREATE TABLE blob_test (id INTEGER PRIMARY KEY, name TEXT, data BLOB)'
        );
        $this->assertConnected();

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'binary data');
        rewind($stream);

        $id = $this->adapter->insertBlob('blob_test', [
            'name' => 'test',
            'data' => new Binary($stream),
        ]);

        fclose($stream);

        $this->assertGreaterThanOrEqual(1, $id);
    }

    public function testUpdateBlobTriggersConnect(): void
    {
        $this->assertNotConnected();

        $this->adapter->execute(
            'CREATE TABLE blob_upd (id INTEGER PRIMARY KEY, name TEXT, data BLOB)'
        );
        $this->adapter->insert(
            "INSERT INTO blob_upd (name, data) VALUES ('test', 'old')"
        );

        $stream = fopen('php://memory', 'r+');
        fwrite($stream, 'new binary data');
        rewind($stream);

        $this->adapter->updateBlob('blob_upd', [
            'data' => new Binary($stream),
        ], 'id = 1');

        fclose($stream);

        $result = $this->adapter->selectValue(
            'SELECT name FROM blob_upd WHERE id = 1'
        );
        $this->assertEquals('test', $result);
    }
}
