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
use Horde\Db\Query\Expr;
use Horde\Db\Query\Expression;

#[CoversClass(Expr::class)]
#[CoversClass(Expression::class)]
class ExprTest extends TestCase
{
    public function testRaw(): void
    {
        $e = Expr::raw('NOW()');
        $this->assertInstanceOf(Expression::class, $e);
        $this->assertSame('NOW()', $e->sql);
        $this->assertSame([], $e->params);
    }

    public function testRawWithParams(): void
    {
        $e = Expr::raw('COALESCE(?, ?)', ['a', 'b']);
        $this->assertSame('COALESCE(?, ?)', $e->sql);
        $this->assertSame(['a', 'b'], $e->params);
    }

    public function testColumn(): void
    {
        $e = Expr::column('users.name');
        $this->assertStringContainsString('users.name', $e->sql);
    }

    public function testCountStar(): void
    {
        $e = Expr::count('*');
        $this->assertSame('COUNT(*)', $e->sql);
    }

    public function testCountColumn(): void
    {
        $e = Expr::count('id');
        $this->assertStringContainsString('COUNT(', $e->sql);
        $this->assertStringContainsString('id', $e->sql);
    }

    public function testCountDistinct(): void
    {
        $e = Expr::countDistinct('status');
        $this->assertStringContainsString('COUNT(DISTINCT', $e->sql);
        $this->assertStringContainsString('status', $e->sql);
    }

    public function testSum(): void
    {
        $e = Expr::sum('amount');
        $this->assertStringContainsString('SUM(', $e->sql);
    }

    public function testAvg(): void
    {
        $e = Expr::avg('price');
        $this->assertStringContainsString('AVG(', $e->sql);
    }

    public function testMin(): void
    {
        $e = Expr::min('created_at');
        $this->assertStringContainsString('MIN(', $e->sql);
    }

    public function testMax(): void
    {
        $e = Expr::max('created_at');
        $this->assertStringContainsString('MAX(', $e->sql);
    }
}
