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

namespace Horde\Db\Test\Integration\Sqlite;

use Horde\Db\Adapter\Pdo\Sqlite;
use Horde\Db\Test\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Integration tests for lazy database connection with real SQLite.
 *
 * Verifies full round-trip behaviour: construction without connection,
 * then real SQL execution on first query.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
#[CoversClass(Sqlite::class)]
class LazyConnectIntegrationTest extends DatabaseTestCase
{
    private Sqlite $conn;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO SQLite extension not available');
        }

        $config = $this->getSqliteConfig();
        $this->conn = new Sqlite($config);
    }

    protected function tearDown(): void
    {
        if (isset($this->conn) && $this->conn->isActive()) {
            $this->conn->disconnect();
        }
    }

    /**
     * Full round-trip: construct, quote (no connect), CREATE, INSERT, SELECT.
     */
    public function testFullRoundTripWithLazyConnect(): void
    {
        // Quoting works before any connection
        $quoted = $this->conn->quoteColumnName('name');
        $this->assertSame('"name"', $quoted);
        $this->assertFalse($this->conn->isActive());

        // First query triggers connection
        $this->conn->execute(
            'CREATE TABLE lazy_int (id INTEGER PRIMARY KEY, name TEXT)'
        );
        $this->assertTrue($this->conn->isActive());

        $this->conn->insert("INSERT INTO lazy_int (name) VALUES ('Alice')");

        $result = $this->conn->selectValue(
            'SELECT name FROM lazy_int WHERE id = 1'
        );
        $this->assertEquals('Alice', $result);
    }

    /**
     * Transaction round-trip: begin (first connect), insert, commit, select.
     */
    public function testTransactionRoundTrip(): void
    {
        $this->assertFalse($this->conn->isActive());

        // beginDbTransaction triggers the first connection
        $this->conn->beginDbTransaction();
        $this->assertTrue($this->conn->isActive());

        $this->conn->execute(
            'CREATE TABLE lazy_txn (id INTEGER PRIMARY KEY, name TEXT)'
        );
        $this->conn->execute("INSERT INTO lazy_txn (name) VALUES ('Bob')");
        $this->conn->commitDbTransaction();

        $count = $this->conn->selectValue('SELECT COUNT(*) FROM lazy_txn');
        $this->assertEquals(1, $count);
    }

    /**
     * quoteString triggers lazy connect and produces correct escaping.
     */
    public function testQuoteStringAfterLazyConnect(): void
    {
        $this->assertFalse($this->conn->isActive());

        $quoted = $this->conn->quoteString("O'Reilly");
        $this->assertTrue($this->conn->isActive());
        $this->assertStringContainsString("O''Reilly", $quoted);
    }
}
