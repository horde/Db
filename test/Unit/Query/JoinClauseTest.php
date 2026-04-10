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
use Horde\Db\Query\JoinClause;
use Horde\Db\Query\Table;
use Horde\Db\Query\Node\ColumnConditionNode;
use Horde\Db\Query\Node\ConditionNode;

#[CoversClass(JoinClause::class)]
class JoinClauseTest extends TestCase
{
    public function testOnCollectsColumnConditionNode(): void
    {
        $join = new JoinClause(new Table('orders'), 'INNER');
        $join->on('contacts.id', '=', 'orders.contact_id');

        $conditions = $join->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertInstanceOf(ColumnConditionNode::class, $conditions[0]);
        $this->assertSame('contacts.id', $conditions[0]->left);
        $this->assertSame('orders.contact_id', $conditions[0]->right);
    }

    public function testOnDefaultsToEquals(): void
    {
        $join = new JoinClause(new Table('orders'), 'INNER');
        $join->on('a', 'b');

        $conditions = $join->getConditions();
        $this->assertSame('=', $conditions[0]->operator);
    }

    public function testOrOnUsesOrBoolean(): void
    {
        $join = new JoinClause(new Table('orders'), 'INNER');
        $join->on('a', '=', 'b')
             ->orOn('c', '=', 'd');

        $conditions = $join->getConditions();
        $this->assertSame('AND', $conditions[0]->boolean);
        $this->assertSame('OR', $conditions[1]->boolean);
    }

    public function testOnValueCollectsConditionNode(): void
    {
        $join = new JoinClause(new Table('orders'), 'LEFT');
        $join->onValue('orders.status', '=', 'active');

        $conditions = $join->getConditions();
        $this->assertCount(1, $conditions);
        $this->assertInstanceOf(ConditionNode::class, $conditions[0]);
        $this->assertSame('orders.status', $conditions[0]->column);
        $this->assertSame('active', $conditions[0]->value);
    }

    public function testGetTableAndType(): void
    {
        $table = Table::as('orders', 'o');
        $join = new JoinClause($table, 'LEFT');

        $this->assertSame($table, $join->getTable());
        $this->assertSame('LEFT', $join->getType());
    }
}
