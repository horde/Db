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
use Horde\Db\Query\WhereClause;
use Horde\Db\Query\Node\ConditionNode;
use Horde\Db\Query\Node\InNode;
use Horde\Db\Query\Node\NullNode;
use Horde\Db\Query\Node\NestedNode;
use Horde\Db\Query\Node\RawNode;
use Horde\Db\Query\Node\ColumnConditionNode;

#[CoversClass(WhereClause::class)]
class WhereClauseTest extends TestCase
{
    public function testWhereCollectsConditionNode(): void
    {
        $clause = new WhereClause();
        $clause->where('status', '=', 'active');

        $nodes = $clause->getNodes();
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(ConditionNode::class, $nodes[0]);
        $this->assertSame('status', $nodes[0]->column);
        $this->assertSame('AND', $nodes[0]->boolean);
    }

    public function testOrWhereCollectsWithOrBoolean(): void
    {
        $clause = new WhereClause();
        $clause->where('a', '=', 1)
            ->orWhere('b', '=', 2);

        $nodes = $clause->getNodes();
        $this->assertCount(2, $nodes);
        $this->assertSame('AND', $nodes[0]->boolean);
        $this->assertSame('OR', $nodes[1]->boolean);
    }

    public function testWhereInCollectsInNode(): void
    {
        $clause = new WhereClause();
        $clause->whereIn('id', [1, 2, 3]);

        $nodes = $clause->getNodes();
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(InNode::class, $nodes[0]);
        $this->assertFalse($nodes[0]->not);
    }

    public function testWhereNotInCollectsInNodeWithNot(): void
    {
        $clause = new WhereClause();
        $clause->whereNotIn('id', [1, 2]);

        $nodes = $clause->getNodes();
        $this->assertInstanceOf(InNode::class, $nodes[0]);
        $this->assertTrue($nodes[0]->not);
    }

    public function testWhereNullCollectsNullNode(): void
    {
        $clause = new WhereClause();
        $clause->whereNull('deleted_at');

        $nodes = $clause->getNodes();
        $this->assertInstanceOf(NullNode::class, $nodes[0]);
        $this->assertFalse($nodes[0]->not);
    }

    public function testWhereNotNullCollectsNullNodeWithNot(): void
    {
        $clause = new WhereClause();
        $clause->whereNotNull('email');

        $nodes = $clause->getNodes();
        $this->assertInstanceOf(NullNode::class, $nodes[0]);
        $this->assertTrue($nodes[0]->not);
    }

    public function testNestedClosureCollectsNestedNode(): void
    {
        $clause = new WhereClause();
        $clause->where(function (WhereClause $w) {
            $w->where('a', '=', 1)
              ->orWhere('b', '=', 2);
        });

        $nodes = $clause->getNodes();
        $this->assertCount(1, $nodes);
        $this->assertInstanceOf(NestedNode::class, $nodes[0]);
        $this->assertCount(2, $nodes[0]->children);
    }

    public function testWhereRawCollectsRawNode(): void
    {
        $clause = new WhereClause();
        $clause->whereRaw('x > ?', [5]);

        $nodes = $clause->getNodes();
        $this->assertInstanceOf(RawNode::class, $nodes[0]);
        $this->assertSame('x > ?', $nodes[0]->sql);
        $this->assertSame([5], $nodes[0]->params);
    }

    public function testWhereColumnCollectsColumnConditionNode(): void
    {
        $clause = new WhereClause();
        $clause->whereColumn('a', '>', 'b');

        $nodes = $clause->getNodes();
        $this->assertInstanceOf(ColumnConditionNode::class, $nodes[0]);
        $this->assertSame('a', $nodes[0]->left);
        $this->assertSame('>', $nodes[0]->operator);
        $this->assertSame('b', $nodes[0]->right);
    }

    public function testWhereColumnDefaultsToEquals(): void
    {
        $clause = new WhereClause();
        $clause->whereColumn('a', 'b');

        $nodes = $clause->getNodes();
        $this->assertInstanceOf(ColumnConditionNode::class, $nodes[0]);
        $this->assertSame('=', $nodes[0]->operator);
    }
}
