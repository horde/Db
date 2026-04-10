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

namespace Horde\Db\Query;

use Closure;
use Horde\Db\Query\Node\BetweenNode;
use Horde\Db\Query\Node\ColumnConditionNode;
use Horde\Db\Query\Node\ConditionNode;
use Horde\Db\Query\Node\InNode;
use Horde\Db\Query\Node\NestedNode;
use Horde\Db\Query\Node\NullNode;
use Horde\Db\Query\Node\RawNode;
use Horde\Db\Query\Node\WhereNode;

/**
 * Mutable where-condition builder used inside closures.
 *
 * Created fresh for each closure call. The parent builder captures the
 * finished node list immutably. This class is mutable because its
 * lifetime is bounded by the closure scope.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
class WhereClause
{
    /** @var WhereNode[] */
    private array $nodes = [];

    public function where(string|Closure $column, string $operator = '=', mixed $value = null): static
    {
        if ($column instanceof Closure) {
            $nested = new self();
            $column($nested);
            $this->nodes[] = new NestedNode($nested->getNodes(), 'AND');
            return $this;
        }

        $this->nodes[] = new ConditionNode($column, $operator, $value, 'AND');
        return $this;
    }

    public function orWhere(string|Closure $column, string $operator = '=', mixed $value = null): static
    {
        if ($column instanceof Closure) {
            $nested = new self();
            $column($nested);
            $this->nodes[] = new NestedNode($nested->getNodes(), 'OR');
            return $this;
        }

        $this->nodes[] = new ConditionNode($column, $operator, $value, 'OR');
        return $this;
    }

    public function whereIn(string $column, array $values): static
    {
        $this->nodes[] = new InNode($column, $values, false, 'AND');
        return $this;
    }

    public function whereNotIn(string $column, array $values): static
    {
        $this->nodes[] = new InNode($column, $values, true, 'AND');
        return $this;
    }

    public function whereBetween(string $column, mixed $low, mixed $high): static
    {
        $this->nodes[] = new BetweenNode($column, $low, $high, false, 'AND');
        return $this;
    }

    public function whereNull(string $column): static
    {
        $this->nodes[] = new NullNode($column, false, 'AND');
        return $this;
    }

    public function whereNotNull(string $column): static
    {
        $this->nodes[] = new NullNode($column, true, 'AND');
        return $this;
    }

    public function whereColumn(string $left, string $operator = '=', ?string $right = null): static
    {
        if ($right === null) {
            $right = $operator;
            $operator = '=';
        }
        $this->nodes[] = new ColumnConditionNode($left, $operator, $right, 'AND');
        return $this;
    }

    public function whereRaw(string $sql, array $params = []): static
    {
        $this->nodes[] = new RawNode($sql, $params, 'AND');
        return $this;
    }

    public function orWhereRaw(string $sql, array $params = []): static
    {
        $this->nodes[] = new RawNode($sql, $params, 'OR');
        return $this;
    }

    /**
     * @return WhereNode[]
     */
    public function getNodes(): array
    {
        return $this->nodes;
    }
}
