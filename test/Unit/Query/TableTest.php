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
use Horde\Db\Query\Table;

#[CoversClass(Table::class)]
class TableTest extends TestCase
{
    public function testNameOnly(): void
    {
        $t = new Table('users');
        $this->assertSame('users', $t->name);
        $this->assertNull($t->alias);
    }

    public function testNameAndAlias(): void
    {
        $t = new Table('users', 'u');
        $this->assertSame('users', $t->name);
        $this->assertSame('u', $t->alias);
    }

    public function testStaticAs(): void
    {
        $t = Table::as('orders', 'o');
        $this->assertSame('orders', $t->name);
        $this->assertSame('o', $t->alias);
    }
}
