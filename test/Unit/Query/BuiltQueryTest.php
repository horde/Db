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

#[CoversClass(BuiltQuery::class)]
class BuiltQueryTest extends TestCase
{
    public function testProperties(): void
    {
        $q = new BuiltQuery('SELECT 1', ['a']);
        $this->assertSame('SELECT 1', $q->sql);
        $this->assertSame(['a'], $q->params);
    }

    public function testDefaultParams(): void
    {
        $q = new BuiltQuery('SELECT 1');
        $this->assertSame([], $q->params);
    }

    public function testToArray(): void
    {
        $q = new BuiltQuery('SELECT ?', [42]);
        $this->assertSame(['SELECT ?', [42]], $q->toArray());
    }
}
