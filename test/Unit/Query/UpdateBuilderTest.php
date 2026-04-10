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
use Horde\Db\Query\UpdateBuilder;
use Horde\Db\Query\WhereClause;
use LogicException;

#[CoversClass(UpdateBuilder::class)]
class UpdateBuilderTest extends TestCase
{
    private function builder(): UpdateBuilder
    {
        return new UpdateBuilder(new AnsiQuotingStub());
    }

    // ── Basic UPDATE ────────────────────────────────────────────────

    public function testSimpleUpdate(): void
    {
        $q = $this->builder()
            ->table('contacts')
            ->set(['name' => 'Updated Name'])
            ->where('id', '=', 42)
            ->build();

        $this->assertSame(
            'UPDATE "contacts" SET "name" = ? WHERE "id" = ?',
            $q->sql,
        );
        $this->assertSame(['Updated Name', 42], $q->params);
    }

    public function testMultipleSetValues(): void
    {
        $q = $this->builder()
            ->table('contacts')
            ->set([
                'name' => 'Alice',
                'email' => 'alice@new.com',
            ])
            ->where('id', '=', 1)
            ->build();

        $this->assertSame(
            'UPDATE "contacts" SET "name" = ?, "email" = ? WHERE "id" = ?',
            $q->sql,
        );
        $this->assertSame(['Alice', 'alice@new.com', 1], $q->params);
    }

    public function testSetWithExpression(): void
    {
        $q = $this->builder()
            ->table('contacts')
            ->set([
                'name' => 'Updated',
                'updated_at' => Expr::raw('NOW()'),
            ])
            ->where('id', '=', 42)
            ->build();

        $this->assertSame(
            'UPDATE "contacts" SET "name" = ?, "updated_at" = NOW() WHERE "id" = ?',
            $q->sql,
        );
        $this->assertSame(['Updated', 42], $q->params);
    }

    public function testSetMergesValues(): void
    {
        $q = $this->builder()
            ->table('users')
            ->set(['name' => 'Alice'])
            ->set(['email' => 'alice@example.com'])
            ->where('id', '=', 1)
            ->build();

        $this->assertStringContainsString('"name" = ?', $q->sql);
        $this->assertStringContainsString('"email" = ?', $q->sql);
        $this->assertSame(['Alice', 'alice@example.com', 1], $q->params);
    }

    public function testSetOverridesPreviousKey(): void
    {
        $q = $this->builder()
            ->table('users')
            ->set(['name' => 'Old'])
            ->set(['name' => 'New'])
            ->where('id', '=', 1)
            ->build();

        $this->assertSame(
            'UPDATE "users" SET "name" = ? WHERE "id" = ?',
            $q->sql,
        );
        $this->assertSame(['New', 1], $q->params);
    }

    // ── Increment / Decrement ───────────────────────────────────────

    public function testIncrement(): void
    {
        $q = $this->builder()
            ->table('users')
            ->increment('login_count')
            ->where('id', '=', 1)
            ->build();

        $this->assertSame(
            'UPDATE "users" SET "login_count" = "login_count" + ? WHERE "id" = ?',
            $q->sql,
        );
        $this->assertSame([1, 1], $q->params);
    }

    public function testIncrementByAmount(): void
    {
        $q = $this->builder()
            ->table('users')
            ->increment('login_count', 5)
            ->where('id', '=', 1)
            ->build();

        $this->assertSame(
            'UPDATE "users" SET "login_count" = "login_count" + ? WHERE "id" = ?',
            $q->sql,
        );
        $this->assertSame([5, 1], $q->params);
    }

    public function testDecrement(): void
    {
        $q = $this->builder()
            ->table('products')
            ->decrement('stock', 10)
            ->where('id', '=', 42)
            ->build();

        $this->assertSame(
            'UPDATE "products" SET "stock" = "stock" - ? WHERE "id" = ?',
            $q->sql,
        );
        $this->assertSame([10, 42], $q->params);
    }

    // ── WHERE support ───────────────────────────────────────────────

    public function testWhereMultiple(): void
    {
        $q = $this->builder()
            ->table('users')
            ->set(['active' => 0])
            ->where('role', '=', 'guest')
            ->where('last_login', '<', '2025-01-01')
            ->build();

        $this->assertSame(
            'UPDATE "users" SET "active" = ? WHERE "role" = ? AND "last_login" < ?',
            $q->sql,
        );
        $this->assertSame([0, 'guest', '2025-01-01'], $q->params);
    }

    public function testWhereIn(): void
    {
        $q = $this->builder()
            ->table('users')
            ->set(['active' => 0])
            ->whereIn('id', [1, 2, 3])
            ->build();

        $this->assertSame(
            'UPDATE "users" SET "active" = ? WHERE "id" IN (?, ?, ?)',
            $q->sql,
        );
        $this->assertSame([0, 1, 2, 3], $q->params);
    }

    public function testWhereNested(): void
    {
        $q = $this->builder()
            ->table('users')
            ->set(['status' => 'review'])
            ->where(function (WhereClause $w) {
                $w->where('role', '=', 'admin')
                  ->orWhere('role', '=', 'moderator');
            })
            ->build();

        $this->assertSame(
            'UPDATE "users" SET "status" = ? WHERE ("role" = ? OR "role" = ?)',
            $q->sql,
        );
        $this->assertSame(['review', 'admin', 'moderator'], $q->params);
    }

    public function testUpdateWithoutWhere(): void
    {
        // UPDATE without WHERE is allowed (unlike DELETE)
        $q = $this->builder()
            ->table('settings')
            ->set(['version' => 2])
            ->build();

        $this->assertSame(
            'UPDATE "settings" SET "version" = ?',
            $q->sql,
        );
        $this->assertSame([2], $q->params);
    }

    // ── Immutability ────────────────────────────────────────────────

    public function testImmutabilityTableReturnsNewInstance(): void
    {
        $a = $this->builder();
        $b = $a->table('users');
        $this->assertNotSame($a, $b);
    }

    public function testImmutabilitySetReturnsNewInstance(): void
    {
        $a = $this->builder()->table('users');
        $b = $a->set(['name' => 'Alice']);
        $this->assertNotSame($a, $b);
    }

    public function testImmutabilityWhereReturnsNewInstance(): void
    {
        $a = $this->builder()->table('users')->set(['name' => 'x']);
        $b = $a->where('id', '=', 1);
        $this->assertNotSame($a, $b);

        // Original should have no WHERE
        $this->assertStringNotContainsString('WHERE', $a->build()->sql);
        $this->assertStringContainsString('WHERE', $b->build()->sql);
    }

    // ── Error cases ─────────────────────────────────────────────────

    public function testBuildWithoutTableThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires a table');
        $this->builder()
            ->set(['name' => 'Alice'])
            ->where('id', '=', 1)
            ->build();
    }

    public function testBuildWithoutSetThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires SET');
        $this->builder()
            ->table('users')
            ->where('id', '=', 1)
            ->build();
    }

    // ── Returns BuiltQuery ──────────────────────────────────────────

    public function testBuildReturnsBuiltQuery(): void
    {
        $result = $this->builder()
            ->table('users')
            ->set(['name' => 'Alice'])
            ->where('id', '=', 1)
            ->build();
        $this->assertInstanceOf(BuiltQuery::class, $result);
    }
}
