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

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Db\Adapter\Base;
use Horde\Db\Adapter\SplitRead;
use Horde\Db\Adapter;
use Horde\Db\Query\QuotingInterface;

/**
 * Tests that Adapter\Base and SplitRead satisfy QuotingInterface.
 *
 * Uses a minimal concrete stub of Adapter\Base to avoid needing a
 * real database connection — only the quoting proxy methods and the
 * schema delegation path are exercised.
 */
#[CoversClass(Base::class)]
#[CoversClass(SplitRead::class)]
class QuotingInterfaceTest extends TestCase
{
    private function createBaseStub(): AdapterStub
    {
        return new AdapterStub();
    }

    // ── instanceof ──────────────────────────────────────────────────

    public function testBaseImplementsQuotingInterface(): void
    {
        $adapter = $this->createBaseStub();
        $this->assertInstanceOf(QuotingInterface::class, $adapter);
    }

    public function testSplitReadImplementsQuotingInterface(): void
    {
        $stub = $this->createBaseStub();
        $split = new SplitRead($stub, $stub);
        $this->assertInstanceOf(QuotingInterface::class, $split);
    }

    // ── quoteColumnName ─────────────────────────────────────────────

    public function testQuoteColumnNameBase(): void
    {
        $adapter = $this->createBaseStub();
        // Base\Schema uses ANSI double-quote quoting
        $this->assertSame('"users"', $adapter->quoteColumnName('users'));
    }

    public function testQuoteColumnNameDotNotation(): void
    {
        $adapter = $this->createBaseStub();
        $this->assertSame('"users.id"', $adapter->quoteColumnName('users.id'));
    }

    public function testQuoteColumnNameSplitRead(): void
    {
        $stub = $this->createBaseStub();
        $split = new SplitRead($stub, $stub);
        $this->assertSame('"name"', $split->quoteColumnName('name'));
    }

    // ── quoteTableName ──────────────────────────────────────────────

    public function testQuoteTableNameBase(): void
    {
        $adapter = $this->createBaseStub();
        $this->assertSame('"contacts"', $adapter->quoteTableName('contacts'));
    }

    public function testQuoteTableNameSplitRead(): void
    {
        $stub = $this->createBaseStub();
        $split = new SplitRead($stub, $stub);
        $this->assertSame('"contacts"', $split->quoteTableName('contacts'));
    }

    // ── quoteTrue / quoteFalse ──────────────────────────────────────

    public function testQuoteTrueBase(): void
    {
        $adapter = $this->createBaseStub();
        $this->assertSame('1', $adapter->quoteTrue());
    }

    public function testQuoteFalseBase(): void
    {
        $adapter = $this->createBaseStub();
        $this->assertSame('0', $adapter->quoteFalse());
    }

    public function testQuoteTrueSplitRead(): void
    {
        $stub = $this->createBaseStub();
        $split = new SplitRead($stub, $stub);
        $this->assertSame('1', $split->quoteTrue());
    }

    public function testQuoteFalseSplitRead(): void
    {
        $stub = $this->createBaseStub();
        $split = new SplitRead($stub, $stub);
        $this->assertSame('0', $split->quoteFalse());
    }

    // ── addLimitOffset ──────────────────────────────────────────────

    public function testAddLimitOffsetBase(): void
    {
        $adapter = $this->createBaseStub();
        $sql = $adapter->addLimitOffset('SELECT *', ['limit' => 10]);
        $this->assertSame('SELECT * LIMIT 10', $sql);
    }

    public function testAddLimitOffsetWithOffset(): void
    {
        $adapter = $this->createBaseStub();
        $sql = $adapter->addLimitOffset('SELECT *', ['limit' => 10, 'offset' => 20]);
        $this->assertSame('SELECT * LIMIT 20, 10', $sql);
    }

    // ── addLock ─────────────────────────────────────────────────────

    public function testAddLockBase(): void
    {
        $adapter = $this->createBaseStub();
        $sql = 'SELECT * FROM users';
        $adapter->addLock($sql, ['lock' => 'FOR UPDATE']);
        $this->assertSame('SELECT * FROM users FOR UPDATE', $sql);
    }
}
