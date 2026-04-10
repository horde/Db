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

namespace Horde\Db\Test\Unit\Query;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Db\Query\BuiltQuery;
use Horde\Db\Query\Expr;
use Horde\Db\Query\Expression;
use Horde\Db\Query\InsertBuilder;
use LogicException;

#[CoversClass(InsertBuilder::class)]
class InsertBuilderTest extends TestCase
{
    private function builder(): InsertBuilder
    {
        return new InsertBuilder(new AnsiQuotingStub());
    }

    // ── Basic insert ────────────────────────────────────────────────

    public function testSingleRowInsert(): void
    {
        $q = $this->builder()
            ->into('contacts')
            ->values([
                'name' => 'Alice',
                'email' => 'alice@example.com',
            ])
            ->build();

        $this->assertSame(
            'INSERT INTO "contacts" ("name", "email") VALUES (?, ?)',
            $q->sql,
        );
        $this->assertSame(['Alice', 'alice@example.com'], $q->params);
    }

    public function testSingleRowWithExpression(): void
    {
        $q = $this->builder()
            ->into('contacts')
            ->values([
                'name' => 'Alice',
                'created_at' => Expr::raw('NOW()'),
            ])
            ->build();

        $this->assertSame(
            'INSERT INTO "contacts" ("name", "created_at") VALUES (?, NOW())',
            $q->sql,
        );
        $this->assertSame(['Alice'], $q->params);
    }

    public function testExpressionWithParams(): void
    {
        $q = $this->builder()
            ->into('geo')
            ->values([
                'point' => new Expression('ST_MakePoint(?, ?)', [1.5, 2.5]),
            ])
            ->build();

        $this->assertSame(
            'INSERT INTO "geo" ("point") VALUES (ST_MakePoint(?, ?))',
            $q->sql,
        );
        $this->assertSame([1.5, 2.5], $q->params);
    }

    // ── Multi-row insert ────────────────────────────────────────────

    public function testMultiRowInsert(): void
    {
        $q = $this->builder()
            ->into('contacts')
            ->columns('name', 'email')
            ->addRow('Alice', 'alice@example.com')
            ->addRow('Bob', 'bob@example.com')
            ->addRow('Carol', 'carol@example.com')
            ->build();

        $this->assertSame(
            'INSERT INTO "contacts" ("name", "email") VALUES (?, ?), (?, ?), (?, ?)',
            $q->sql,
        );
        $this->assertSame(
            ['Alice', 'alice@example.com', 'Bob', 'bob@example.com', 'Carol', 'carol@example.com'],
            $q->params,
        );
    }

    public function testMultiRowWithExpression(): void
    {
        $q = $this->builder()
            ->into('events')
            ->columns('name', 'created_at')
            ->addRow('Event A', Expr::raw('NOW()'))
            ->addRow('Event B', Expr::raw('NOW()'))
            ->build();

        $this->assertSame(
            'INSERT INTO "events" ("name", "created_at") VALUES (?, NOW()), (?, NOW())',
            $q->sql,
        );
        $this->assertSame(['Event A', 'Event B'], $q->params);
    }

    // ── Immutability ────────────────────────────────────────────────

    public function testImmutabilityIntoReturnsNewInstance(): void
    {
        $a = $this->builder();
        $b = $a->into('contacts');
        $this->assertNotSame($a, $b);
    }

    public function testImmutabilityValuesReturnsNewInstance(): void
    {
        $a = $this->builder()->into('contacts');
        $b = $a->values(['name' => 'Alice']);
        $this->assertNotSame($a, $b);
    }

    public function testImmutabilityAddRowReturnsNewInstance(): void
    {
        $a = $this->builder()->into('contacts')->columns('name');
        $b = $a->addRow('Alice');
        $c = $b->addRow('Bob');
        $this->assertNotSame($a, $b);
        $this->assertNotSame($b, $c);
    }

    // ── Error cases ─────────────────────────────────────────────────

    public function testBuildWithoutTableThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires a table');
        $this->builder()
            ->values(['name' => 'Alice'])
            ->build();
    }

    public function testBuildWithoutValuesThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires values');
        $this->builder()
            ->into('contacts')
            ->build();
    }

    public function testMultiRowWithoutColumnsThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires columns');
        $this->builder()
            ->into('contacts')
            ->addRow('Alice', 'alice@example.com')
            ->build();
    }

    // ── Returns BuiltQuery ──────────────────────────────────────────

    public function testBuildReturnsBuiltQuery(): void
    {
        $result = $this->builder()
            ->into('contacts')
            ->values(['name' => 'Alice'])
            ->build();
        $this->assertInstanceOf(BuiltQuery::class, $result);
    }
}
