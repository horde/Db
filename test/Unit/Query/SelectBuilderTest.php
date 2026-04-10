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
use Horde\Db\Query\JoinClause;
use Horde\Db\Query\SelectBuilder;
use Horde\Db\Query\Table;
use Horde\Db\Query\WhereClause;

#[CoversClass(SelectBuilder::class)]
class SelectBuilderTest extends TestCase
{
    private function builder(): SelectBuilder
    {
        return new SelectBuilder(new AnsiQuotingStub());
    }

    // ── Immutability ─────────────────────────────────────────────────

    public function testImmutabilityWhereReturnsNewInstance(): void
    {
        $a = $this->builder()->from('users');
        $b = $a->where('id', '=', 1);

        $this->assertNotSame($a, $b);
        // Original should have no WHERE
        $this->assertSame('SELECT * FROM "users"', $a->build()->sql);
        // Derived should have WHERE
        $this->assertStringContainsString('WHERE', $b->build()->sql);
    }

    public function testImmutabilityColumnsReturnsNewInstance(): void
    {
        $a = $this->builder()->from('users');
        $b = $a->columns('id', 'name');

        $this->assertSame('SELECT * FROM "users"', $a->build()->sql);
        $this->assertStringContainsString('"id"', $b->build()->sql);
    }

    // ── Basic SELECT ─────────────────────────────────────────────────

    public function testSelectStar(): void
    {
        $q = $this->builder()->from('users')->build();
        $this->assertSame('SELECT * FROM "users"', $q->sql);
        $this->assertSame([], $q->params);
    }

    public function testSelectColumns(): void
    {
        $q = $this->builder()
            ->columns('id', 'name')
            ->from('users')
            ->build();
        $this->assertSame('SELECT "id", "name" FROM "users"', $q->sql);
    }

    public function testSelectColumnWithAlias(): void
    {
        $q = $this->builder()
            ->column('name', 'full_name')
            ->from('users')
            ->build();
        $this->assertSame('SELECT "name" AS "full_name" FROM "users"', $q->sql);
    }

    public function testAddColumns(): void
    {
        $q = $this->builder()
            ->columns('id')
            ->addColumns('name', 'email')
            ->from('users')
            ->build();
        $this->assertSame('SELECT "id", "name", "email" FROM "users"', $q->sql);
    }

    public function testSelectWithExpression(): void
    {
        $q = $this->builder()
            ->column(Expr::count('*'), 'total')
            ->from('users')
            ->build();
        // Expression column ignores the alias argument (not a string column)
        $this->assertStringContainsString('COUNT(*)', $q->sql);
    }

    // ── FROM ─────────────────────────────────────────────────────────

    public function testFromWithAlias(): void
    {
        $q = $this->builder()
            ->from('contacts', 'c')
            ->build();
        $this->assertSame('SELECT * FROM "contacts" "c"', $q->sql);
    }

    public function testFromWithTableObject(): void
    {
        $q = $this->builder()
            ->from(Table::as('contacts', 'c'))
            ->build();
        $this->assertSame('SELECT * FROM "contacts" "c"', $q->sql);
    }

    // ── WHERE ────────────────────────────────────────────────────────

    public function testWhereSimple(): void
    {
        $q = $this->builder()
            ->from('users')
            ->where('status', '=', 'active')
            ->build();
        $this->assertSame('SELECT * FROM "users" WHERE "status" = ?', $q->sql);
        $this->assertSame(['active'], $q->params);
    }

    public function testWhereMultiple(): void
    {
        $q = $this->builder()
            ->from('users')
            ->where('status', '=', 'active')
            ->where('age', '>', 25)
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" WHERE "status" = ? AND "age" > ?',
            $q->sql,
        );
        $this->assertSame(['active', 25], $q->params);
    }

    public function testOrWhere(): void
    {
        $q = $this->builder()
            ->from('users')
            ->where('a', '=', 1)
            ->orWhere('b', '=', 2)
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" WHERE "a" = ? OR "b" = ?',
            $q->sql,
        );
    }

    public function testWhereIn(): void
    {
        $q = $this->builder()
            ->from('users')
            ->whereIn('id', [1, 2, 3])
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" WHERE "id" IN (?, ?, ?)',
            $q->sql,
        );
        $this->assertSame([1, 2, 3], $q->params);
    }

    public function testWhereNotIn(): void
    {
        $q = $this->builder()
            ->from('users')
            ->whereNotIn('status', ['deleted', 'banned'])
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" WHERE "status" NOT IN (?, ?)',
            $q->sql,
        );
    }

    public function testWhereBetween(): void
    {
        $q = $this->builder()
            ->from('users')
            ->whereBetween('age', 18, 65)
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" WHERE "age" BETWEEN ? AND ?',
            $q->sql,
        );
        $this->assertSame([18, 65], $q->params);
    }

    public function testWhereNull(): void
    {
        $q = $this->builder()
            ->from('users')
            ->whereNull('deleted_at')
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" WHERE "deleted_at" IS NULL',
            $q->sql,
        );
    }

    public function testWhereNotNull(): void
    {
        $q = $this->builder()
            ->from('users')
            ->whereNotNull('email')
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" WHERE "email" IS NOT NULL',
            $q->sql,
        );
    }

    public function testWhereColumn(): void
    {
        $q = $this->builder()
            ->from('users')
            ->whereColumn('created_at', '>', 'updated_at')
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" WHERE "created_at" > "updated_at"',
            $q->sql,
        );
        $this->assertSame([], $q->params);
    }

    public function testWhereColumnDefaultsToEquals(): void
    {
        $q = $this->builder()
            ->from('users')
            ->whereColumn('first_name', 'last_name')
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" WHERE "first_name" = "last_name"',
            $q->sql,
        );
    }

    public function testWhereNested(): void
    {
        $q = $this->builder()
            ->from('users')
            ->where(function (WhereClause $w) {
                $w->where('name', 'LIKE', '%smith%')
                  ->orWhere('name', 'LIKE', '%jones%');
            })
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" WHERE ("name" LIKE ? OR "name" LIKE ?)',
            $q->sql,
        );
        $this->assertSame(['%smith%', '%jones%'], $q->params);
    }

    public function testOrWhereNested(): void
    {
        $q = $this->builder()
            ->from('users')
            ->where('active', '=', 1)
            ->orWhere(function (WhereClause $w) {
                $w->where('role', '=', 'admin')
                  ->where('verified', '=', true);
            })
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" WHERE "active" = ? OR ("role" = ? AND "verified" = ?)',
            $q->sql,
        );
    }

    public function testWhereRaw(): void
    {
        $q = $this->builder()
            ->from('users')
            ->whereRaw('ST_DWithin(location, ST_MakePoint(?, ?), ?)', [1.0, 2.0, 100])
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" WHERE ST_DWithin(location, ST_MakePoint(?, ?), ?)',
            $q->sql,
        );
        $this->assertSame([1.0, 2.0, 100], $q->params);
    }

    public function testOrWhereRaw(): void
    {
        $q = $this->builder()
            ->from('users')
            ->where('id', '=', 1)
            ->orWhereRaw('custom_check(?) = 1', ['val'])
            ->build();
        $this->assertStringContainsString('OR custom_check(?) = 1', $q->sql);
    }

    public function testWhereExists(): void
    {
        $q = $this->builder()
            ->from('contacts')
            ->whereExists(function (SelectBuilder $sub) {
                return $sub->columns(Expr::raw('1'))
                    ->from('orders')
                    ->whereColumn('orders.contact_id', '=', 'contacts.id');
            })
            ->build();
        $this->assertStringContainsString('EXISTS (SELECT 1 FROM "orders"', $q->sql);
        $this->assertStringContainsString(
            '"orders"."contact_id" = "contacts"."id"',
            $q->sql,
        );
    }

    public function testWhereNotExists(): void
    {
        $q = $this->builder()
            ->from('contacts')
            ->whereNotExists(function (SelectBuilder $sub) {
                return $sub->columns(Expr::raw('1'))
                    ->from('orders')
                    ->whereColumn('orders.contact_id', '=', 'contacts.id');
            })
            ->build();
        $this->assertStringContainsString('NOT EXISTS (SELECT 1 FROM "orders"', $q->sql);
    }

    // ── JOINs ────────────────────────────────────────────────────────

    public function testInnerJoin(): void
    {
        $q = $this->builder()
            ->from('contacts')
            ->join('orders', 'contacts.id', '=', 'orders.contact_id')
            ->build();
        $this->assertSame(
            'SELECT * FROM "contacts" INNER JOIN "orders" ON "contacts"."id" = "orders"."contact_id"',
            $q->sql,
        );
    }

    public function testLeftJoin(): void
    {
        $q = $this->builder()
            ->from('contacts')
            ->leftJoin('addresses', 'contacts.id', '=', 'addresses.contact_id')
            ->build();
        $this->assertStringContainsString('LEFT JOIN "addresses"', $q->sql);
    }

    public function testJoinWithAlias(): void
    {
        $q = $this->builder()
            ->from('contacts', 'c')
            ->join(Table::as('orders', 'o'), 'c.id', '=', 'o.contact_id')
            ->build();
        $this->assertStringContainsString('INNER JOIN "orders" "o"', $q->sql);
        $this->assertStringContainsString('"c"."id" = "o"."contact_id"', $q->sql);
    }

    public function testJoinWithClosure(): void
    {
        $q = $this->builder()
            ->from('contacts')
            ->join('orders', function (JoinClause $j) {
                $j->on('contacts.id', '=', 'orders.contact_id')
                  ->onValue('orders.status', '=', 'active');
            })
            ->build();
        $this->assertStringContainsString(
            'ON "contacts"."id" = "orders"."contact_id" AND "orders"."status" = ?',
            $q->sql,
        );
        $this->assertSame(['active'], $q->params);
    }

    public function testCrossJoin(): void
    {
        $q = $this->builder()
            ->from('users')
            ->crossJoin('colors')
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" CROSS JOIN "colors"',
            $q->sql,
        );
    }

    // ── ORDER BY ─────────────────────────────────────────────────────

    public function testOrderBy(): void
    {
        $q = $this->builder()
            ->from('users')
            ->orderBy('name')
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" ORDER BY "name" ASC',
            $q->sql,
        );
    }

    public function testOrderByDesc(): void
    {
        $q = $this->builder()
            ->from('users')
            ->orderBy('created_at', 'DESC')
            ->build();
        $this->assertStringContainsString('ORDER BY "created_at" DESC', $q->sql);
    }

    public function testAddOrderBy(): void
    {
        $q = $this->builder()
            ->from('users')
            ->orderBy('name')
            ->addOrderBy('id')
            ->build();
        $this->assertSame(
            'SELECT * FROM "users" ORDER BY "name" ASC, "id" ASC',
            $q->sql,
        );
    }

    public function testOrderByRaw(): void
    {
        $q = $this->builder()
            ->from('users')
            ->orderByRaw('FIELD(status, ?, ?, ?)', ['urgent', 'active', 'closed'])
            ->build();
        $this->assertStringContainsString('ORDER BY FIELD(status, ?, ?, ?)', $q->sql);
        $this->assertSame(['urgent', 'active', 'closed'], $q->params);
    }

    // ── LIMIT / OFFSET ──────────────────────────────────────────────

    public function testLimit(): void
    {
        $q = $this->builder()
            ->from('users')
            ->limit(20)
            ->build();
        $this->assertSame('SELECT * FROM "users" LIMIT 20', $q->sql);
    }

    public function testLimitOffset(): void
    {
        $q = $this->builder()
            ->from('users')
            ->limit(20)
            ->offset(40)
            ->build();
        $this->assertSame('SELECT * FROM "users" LIMIT 20 OFFSET 40', $q->sql);
    }

    // ── GROUP BY / HAVING ────────────────────────────────────────────

    public function testGroupBy(): void
    {
        $q = $this->builder()
            ->columns('status', Expr::count('*'))
            ->from('users')
            ->groupBy('status')
            ->build();
        $this->assertStringContainsString('GROUP BY "status"', $q->sql);
    }

    public function testHaving(): void
    {
        $q = $this->builder()
            ->columns('status', Expr::count('*'))
            ->from('users')
            ->groupBy('status')
            ->having('count(*)', '>', 5)
            ->build();
        $this->assertStringContainsString('HAVING "count(*)" > ?', $q->sql);
        $this->assertSame([5], $q->params);
    }

    public function testHavingRaw(): void
    {
        $q = $this->builder()
            ->columns('status')
            ->from('users')
            ->groupBy('status')
            ->havingRaw('SUM(amount) > ?', [1000])
            ->build();
        $this->assertStringContainsString('HAVING SUM(amount) > ?', $q->sql);
        $this->assertSame([1000], $q->params);
    }

    // ── DISTINCT ─────────────────────────────────────────────────────

    public function testDistinct(): void
    {
        $q = $this->builder()
            ->from('users')
            ->distinct()
            ->build();
        $this->assertSame('SELECT DISTINCT * FROM "users"', $q->sql);
    }

    // ── LOCKING ──────────────────────────────────────────────────────

    public function testForUpdate(): void
    {
        $q = $this->builder()
            ->from('users')
            ->where('id', '=', 1)
            ->forUpdate()
            ->build();
        $this->assertStringContainsString('FOR UPDATE', $q->sql);
    }

    // ── Base query reuse (the immutability payoff) ───────────────────

    public function testBaseQueryReuse(): void
    {
        $base = $this->builder()
            ->from('users')
            ->where('active', '=', 1)
            ->orderBy('name');

        $byOrg = $base->where('org_id', '=', 42)->build();
        $recent = $base->orderBy('created_at', 'DESC')->limit(10)->build();

        // Base is untouched
        $baseSql = $base->build()->sql;
        $this->assertStringNotContainsString('org_id', $baseSql);
        $this->assertStringNotContainsString('LIMIT', $baseSql);

        // Derived queries have their additions
        $this->assertStringContainsString('"org_id"', $byOrg->sql);
        $this->assertSame([1, 42], $byOrg->params);
        $this->assertStringContainsString('LIMIT 10', $recent->sql);
    }

    // ── Complex combined query ───────────────────────────────────────

    public function testComplexQuery(): void
    {
        $q = $this->builder()
            ->columns('c.id', 'c.name', 'c.email')
            ->from('contacts', 'c')
            ->join(Table::as('orders', 'o'), 'c.id', '=', 'o.contact_id')
            ->leftJoin('addresses', 'c.id', '=', 'addresses.contact_id')
            ->where('c.status', '=', 'active')
            ->where('c.age', '>', 25)
            ->whereIn('c.role', ['admin', 'manager'])
            ->whereNotNull('c.email')
            ->orderBy('c.name')
            ->addOrderBy('c.id')
            ->limit(20)
            ->offset(40)
            ->build();

        $this->assertStringContainsString('SELECT "c"."id", "c"."name", "c"."email"', $q->sql);
        $this->assertStringContainsString('FROM "contacts" "c"', $q->sql);
        $this->assertStringContainsString('INNER JOIN "orders" "o"', $q->sql);
        $this->assertStringContainsString('LEFT JOIN "addresses"', $q->sql);
        $this->assertStringContainsString('WHERE "c"."status" = ?', $q->sql);
        $this->assertStringContainsString('AND "c"."age" > ?', $q->sql);
        $this->assertStringContainsString('"c"."role" IN (?, ?)', $q->sql);
        $this->assertStringContainsString('"c"."email" IS NOT NULL', $q->sql);
        $this->assertStringContainsString('ORDER BY "c"."name" ASC, "c"."id" ASC', $q->sql);
        $this->assertStringContainsString('LIMIT 20 OFFSET 40', $q->sql);

        $this->assertSame(['active', 25, 'admin', 'manager'], $q->params);
    }

    // ── BuiltQuery is returned ───────────────────────────────────────

    public function testBuildReturnsBuiltQuery(): void
    {
        $result = $this->builder()->from('users')->build();
        $this->assertInstanceOf(BuiltQuery::class, $result);
    }
}
