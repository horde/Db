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
use Horde\Db\Query\Node\ExistsNode;
use Horde\Db\Query\Node\InNode;
use Horde\Db\Query\Node\NestedNode;
use Horde\Db\Query\Node\NullNode;
use Horde\Db\Query\Node\RawNode;
use Horde\Db\Query\Node\WhereNode;

/**
 * Trait providing immutable WHERE clause methods and node rendering.
 *
 * Used by SelectBuilder, UpdateBuilder, and DeleteBuilder. Expects the
 * using class to have:
 * - private array $wheres
 * - private readonly QuotingInterface $quoter
 *
 * All methods are immutable: they clone $this, modify the clone, return it.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
trait HasWhere
{
    public function where(string|Closure $column, string $operator = '=', mixed $value = null): static
    {
        $new = clone $this;

        if ($column instanceof Closure) {
            $clause = new WhereClause();
            $column($clause);
            $new->wheres[] = new NestedNode($clause->getNodes(), 'AND');
            return $new;
        }

        $new->wheres[] = new ConditionNode($column, $operator, $value, 'AND');
        return $new;
    }

    public function orWhere(string|Closure $column, string $operator = '=', mixed $value = null): static
    {
        $new = clone $this;

        if ($column instanceof Closure) {
            $clause = new WhereClause();
            $column($clause);
            $new->wheres[] = new NestedNode($clause->getNodes(), 'OR');
            return $new;
        }

        $new->wheres[] = new ConditionNode($column, $operator, $value, 'OR');
        return $new;
    }

    public function whereIn(string $column, array $values): static
    {
        $new = clone $this;
        $new->wheres[] = new InNode($column, $values, false, 'AND');
        return $new;
    }

    public function whereNotIn(string $column, array $values): static
    {
        $new = clone $this;
        $new->wheres[] = new InNode($column, $values, true, 'AND');
        return $new;
    }

    public function whereBetween(string $column, mixed $low, mixed $high): static
    {
        $new = clone $this;
        $new->wheres[] = new BetweenNode($column, $low, $high, false, 'AND');
        return $new;
    }

    public function whereNull(string $column): static
    {
        $new = clone $this;
        $new->wheres[] = new NullNode($column, false, 'AND');
        return $new;
    }

    public function whereNotNull(string $column): static
    {
        $new = clone $this;
        $new->wheres[] = new NullNode($column, true, 'AND');
        return $new;
    }

    public function whereColumn(string $left, string $operator = '=', ?string $right = null): static
    {
        $new = clone $this;
        if ($right === null) {
            $right = $operator;
            $operator = '=';
        }
        $new->wheres[] = new ColumnConditionNode($left, $operator, $right, 'AND');
        return $new;
    }

    public function whereExists(Closure $callback): static
    {
        $new = clone $this;
        $sub = new SelectBuilder($this->quoter);
        $result = $callback($sub);
        if ($result instanceof SelectBuilder) {
            $sub = $result;
        }
        $new->wheres[] = new ExistsNode($sub, false, 'AND');
        return $new;
    }

    public function whereNotExists(Closure $callback): static
    {
        $new = clone $this;
        $sub = new SelectBuilder($this->quoter);
        $result = $callback($sub);
        if ($result instanceof SelectBuilder) {
            $sub = $result;
        }
        $new->wheres[] = new ExistsNode($sub, true, 'AND');
        return $new;
    }

    public function whereRaw(string $sql, array $params = []): static
    {
        $new = clone $this;
        $new->wheres[] = new RawNode($sql, $params, 'AND');
        return $new;
    }

    public function orWhereRaw(string $sql, array $params = []): static
    {
        $new = clone $this;
        $new->wheres[] = new RawNode($sql, $params, 'OR');
        return $new;
    }

    // ── Internal rendering ───────────────────────────────────────────

    private function buildWhere(string &$sql, array &$params): void
    {
        if (empty($this->wheres)) {
            return;
        }

        $sql .= ' WHERE ';
        $this->renderNodes($this->wheres, $sql, $params);
    }

    /**
     * Render a list of WhereNodes to SQL, joining with AND/OR.
     */
    private function renderNodes(array $nodes, string &$sql, array &$params): void
    {
        foreach ($nodes as $i => $node) {
            if ($i > 0) {
                $sql .= ' ' . $node->boolean . ' ';
            }

            if ($node instanceof ConditionNode) {
                $sql .= $this->quoter->quoteColumnName($node->column);
                $sql .= ' ' . $node->operator . ' ';
                if ($node->value instanceof Expression) {
                    $sql .= $node->value->sql;
                    array_push($params, ...$node->value->params);
                } else {
                    $sql .= '?';
                    $params[] = $node->value;
                }
            } elseif ($node instanceof ColumnConditionNode) {
                $sql .= $this->quoter->quoteColumnName($node->left);
                $sql .= ' ' . $node->operator . ' ';
                $sql .= $this->quoter->quoteColumnName($node->right);
            } elseif ($node instanceof RawNode) {
                $sql .= $node->sql;
                array_push($params, ...$node->params);
            } elseif ($node instanceof NestedNode) {
                $sql .= '(';
                $this->renderNodes($node->children, $sql, $params);
                $sql .= ')';
            } elseif ($node instanceof InNode) {
                $sql .= $this->quoter->quoteColumnName($node->column);
                $sql .= $node->not ? ' NOT IN (' : ' IN (';
                $placeholders = array_fill(0, count($node->values), '?');
                $sql .= implode(', ', $placeholders);
                $sql .= ')';
                array_push($params, ...$node->values);
            } elseif ($node instanceof NullNode) {
                $sql .= $this->quoter->quoteColumnName($node->column);
                $sql .= $node->not ? ' IS NOT NULL' : ' IS NULL';
            } elseif ($node instanceof BetweenNode) {
                $sql .= $this->quoter->quoteColumnName($node->column);
                $sql .= $node->not ? ' NOT BETWEEN ' : ' BETWEEN ';
                $sql .= '? AND ?';
                $params[] = $node->low;
                $params[] = $node->high;
            } elseif ($node instanceof ExistsNode) {
                $sql .= $node->not ? 'NOT EXISTS (' : 'EXISTS (';
                $sub = $node->subquery->build();
                $sql .= $sub->sql;
                array_push($params, ...$sub->params);
                $sql .= ')';
            }
        }
    }
}
