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
use Horde\Db\Query\DeleteBuilder;
use Horde\Db\Query\WhereClause;
use LogicException;

#[CoversClass(DeleteBuilder::class)]
class DeleteBuilderTest extends TestCase
{
    private function builder(): DeleteBuilder
    {
        return new DeleteBuilder(new AnsiQuotingStub());
    }

    // ── Basic DELETE ────────────────────────────────────────────────

    public function testSimpleDelete(): void
    {
        $q = $this->builder()
            ->from('contacts')
            ->where('id', '=', 42)
            ->build();

        $this->assertSame(
            'DELETE FROM "contacts" WHERE "id" = ?',
            $q->sql,
        );
        $this->assertSame([42], $q->params);
    }

    public function testDeleteWithMultipleWhere(): void
    {
        $q = $this->builder()
            ->from('logs')
            ->where('level', '=', 'debug')
            ->where('created_at', '<', '2025-01-01')
            ->build();

        $this->assertSame(
            'DELETE FROM "logs" WHERE "level" = ? AND "created_at" < ?',
            $q->sql,
        );
        $this->assertSame(['debug', '2025-01-01'], $q->params);
    }

    public function testDeleteWithWhereIn(): void
    {
        $q = $this->builder()
            ->from('users')
            ->whereIn('id', [1, 2, 3])
            ->build();

        $this->assertSame(
            'DELETE FROM "users" WHERE "id" IN (?, ?, ?)',
            $q->sql,
        );
        $this->assertSame([1, 2, 3], $q->params);
    }

    public function testDeleteWithNestedWhere(): void
    {
        $q = $this->builder()
            ->from('users')
            ->where(function (WhereClause $w) {
                $w->where('status', '=', 'banned')
                  ->orWhere('status', '=', 'deleted');
            })
            ->build();

        $this->assertSame(
            'DELETE FROM "users" WHERE ("status" = ? OR "status" = ?)',
            $q->sql,
        );
        $this->assertSame(['banned', 'deleted'], $q->params);
    }

    public function testDeleteWithWhereNull(): void
    {
        $q = $this->builder()
            ->from('sessions')
            ->whereNull('user_id')
            ->build();

        $this->assertSame(
            'DELETE FROM "sessions" WHERE "user_id" IS NULL',
            $q->sql,
        );
    }

    // ── Safety: dangerouslyDeleteAll ─────────────────────────────────

    public function testDangerouslyDeleteAll(): void
    {
        $q = $this->builder()
            ->from('temp_data')
            ->dangerouslyDeleteAll()
            ->build();

        $this->assertSame('DELETE FROM "temp_data"', $q->sql);
        $this->assertSame([], $q->params);
    }

    public function testDeleteAllWithWhereStillWorks(): void
    {
        // dangerouslyDeleteAll + where is fine — the where just narrows it
        $q = $this->builder()
            ->from('temp_data')
            ->dangerouslyDeleteAll()
            ->where('old', '=', true)
            ->build();

        $this->assertSame(
            'DELETE FROM "temp_data" WHERE "old" = ?',
            $q->sql,
        );
        $this->assertSame([true], $q->params);
    }

    // ── Immutability ────────────────────────────────────────────────

    public function testImmutabilityFromReturnsNewInstance(): void
    {
        $a = $this->builder();
        $b = $a->from('contacts');
        $this->assertNotSame($a, $b);
    }

    public function testImmutabilityWhereReturnsNewInstance(): void
    {
        $a = $this->builder()->from('contacts');
        $b = $a->where('id', '=', 1);
        $this->assertNotSame($a, $b);
    }

    public function testImmutabilityBaseQueryReuse(): void
    {
        $base = $this->builder()->from('logs');

        $byLevel = $base->where('level', '=', 'error')->build();
        $byDate = $base->where('created_at', '<', '2025-01-01')->build();

        $this->assertStringContainsString('"level"', $byLevel->sql);
        $this->assertStringNotContainsString('created_at', $byLevel->sql);

        $this->assertStringContainsString('"created_at"', $byDate->sql);
        $this->assertStringNotContainsString('level', $byDate->sql);
    }

    // ── Error cases ─────────────────────────────────────────────────

    public function testBuildWithoutTableThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires a table');
        $this->builder()
            ->where('id', '=', 1)
            ->build();
    }

    public function testBuildWithoutWhereThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('prevent accidental');
        $this->builder()
            ->from('contacts')
            ->build();
    }

    // ── Returns BuiltQuery ──────────────────────────────────────────

    public function testBuildReturnsBuiltQuery(): void
    {
        $result = $this->builder()
            ->from('contacts')
            ->where('id', '=', 1)
            ->build();
        $this->assertInstanceOf(BuiltQuery::class, $result);
    }
}
