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
 * Immutable fluent SELECT query builder.
 *
 * Each method returns a new builder instance, leaving the original unchanged.
 * Chained calls look identical to mutable builders. Non-chained usage enables
 * base query reuse — define a query shape once, derive per-request variants.
 *
 * The builder produces a BuiltQuery (SQL + params) via build(). It never
 * executes SQL — that is the adapter's job.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
class SelectBuilder
{
    private bool $distinct = false;

    /** @var array<int, string|Expression> */
    private array $columns = [];

    private ?Table $table = null;

    /** @var JoinClause[] */
    private array $joins = [];

    /** @var WhereNode[] */
    private array $wheres = [];

    /** @var array<int, array{string|Expression, string}> column, direction */
    private array $orderBy = [];

    private ?int $limit = null;
    private ?int $offset = null;

    /** @var string[] */
    private array $groupBy = [];

    /** @var WhereNode[] */
    private array $havings = [];

    private ?string $lock = null;

    public function __construct(
        private readonly QuotingInterface $quoter,
    ) {}

    // ── Column selection ─────────────────────────────────────────────

    /**
     * Set the columns to select (replaces any previous columns).
     */
    public function columns(string|Expression ...$columns): static
    {
        $new = clone $this;
        $new->columns = array_values($columns);
        return $new;
    }

    /**
     * Add a single column with optional alias.
     */
    public function column(string|Expression $column, ?string $alias = null): static
    {
        $new = clone $this;
        if ($alias !== null && is_string($column)) {
            $column = $column . ' AS ' . $alias;
        }
        $new->columns[] = $column;
        return $new;
    }

    /**
     * Append columns without replacing existing ones.
     */
    public function addColumns(string|Expression ...$columns): static
    {
        $new = clone $this;
        $new->columns = array_merge($new->columns, array_values($columns));
        return $new;
    }

    // ── FROM ─────────────────────────────────────────────────────────

    /**
     * Set the table to select from.
     */
    public function from(string|Table $table, ?string $alias = null): static
    {
        $new = clone $this;
        if (is_string($table)) {
            $table = new Table($table, $alias);
        }
        $new->table = $table;
        return $new;
    }

    // ── JOINs ────────────────────────────────────────────────────────

    /**
     * Add an INNER JOIN.
     *
     * @param string|Table    $table  Table or Table::as() for alias.
     * @param string|Closure  $left   Left column, or closure for complex ON.
     * @param string          $op     Operator (when using column args).
     * @param string          $right  Right column (when using column args).
     */
    public function join(string|Table $table, string|Closure $left, string $op = '=', string $right = ''): static
    {
        return $this->addJoin('INNER', $table, $left, $op, $right);
    }

    public function leftJoin(string|Table $table, string|Closure $left, string $op = '=', string $right = ''): static
    {
        return $this->addJoin('LEFT', $table, $left, $op, $right);
    }

    public function rightJoin(string|Table $table, string|Closure $left, string $op = '=', string $right = ''): static
    {
        return $this->addJoin('RIGHT', $table, $left, $op, $right);
    }

    public function crossJoin(string|Table $table): static
    {
        $new = clone $this;
        $tbl = is_string($table) ? new Table($table) : $table;
        $new->joins[] = new JoinClause($tbl, 'CROSS');
        return $new;
    }

    private function addJoin(string $type, string|Table $table, string|Closure $left, string $op, string $right): static
    {
        $new = clone $this;
        $tbl = is_string($table) ? new Table($table) : $table;
        $join = new JoinClause($tbl, $type);

        if ($left instanceof Closure) {
            $left($join);
        } else {
            $join->on($left, $op, $right);
        }

        $new->joins[] = $join;
        return $new;
    }

    // ── WHERE ────────────────────────────────────────────────────────

    /**
     * Add a WHERE condition.
     *
     * Accepts column/op/value, column/value (defaults to =), or a closure
     * for grouped (parenthesized) conditions.
     */
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
        $sub = new self($this->quoter);
        $result = $callback($sub);
        if ($result instanceof self) {
            $sub = $result;
        }
        $new->wheres[] = new ExistsNode($sub, false, 'AND');
        return $new;
    }

    public function whereNotExists(Closure $callback): static
    {
        $new = clone $this;
        $sub = new self($this->quoter);
        $result = $callback($sub);
        if ($result instanceof self) {
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

    // ── ORDER BY ─────────────────────────────────────────────────────

    /**
     * Set ordering (replaces any previous order).
     */
    public function orderBy(string|Expression $column, string $direction = 'ASC'): static
    {
        $new = clone $this;
        $new->orderBy = [[$column, strtoupper($direction)]];
        return $new;
    }

    /**
     * Append ordering without replacing existing.
     */
    public function addOrderBy(string|Expression $column, string $direction = 'ASC'): static
    {
        $new = clone $this;
        $new->orderBy[] = [$column, strtoupper($direction)];
        return $new;
    }

    public function orderByRaw(string $sql, array $params = []): static
    {
        $new = clone $this;
        $new->orderBy = [[new Expression($sql, $params), '']];
        return $new;
    }

    // ── LIMIT / OFFSET ──────────────────────────────────────────────

    public function limit(int $limit): static
    {
        $new = clone $this;
        $new->limit = $limit;
        return $new;
    }

    public function offset(int $offset): static
    {
        $new = clone $this;
        $new->offset = $offset;
        return $new;
    }

    // ── GROUP BY / HAVING ────────────────────────────────────────────

    public function groupBy(string ...$columns): static
    {
        $new = clone $this;
        $new->groupBy = array_values($columns);
        return $new;
    }

    public function having(string $column, string $operator, mixed $value): static
    {
        $new = clone $this;
        $new->havings[] = new ConditionNode($column, $operator, $value, 'AND');
        return $new;
    }

    public function havingRaw(string $sql, array $params = []): static
    {
        $new = clone $this;
        $new->havings[] = new RawNode($sql, $params, 'AND');
        return $new;
    }

    // ── DISTINCT ─────────────────────────────────────────────────────

    public function distinct(): static
    {
        $new = clone $this;
        $new->distinct = true;
        return $new;
    }

    // ── LOCKING ──────────────────────────────────────────────────────

    public function forUpdate(): static
    {
        $new = clone $this;
        $new->lock = 'FOR UPDATE';
        return $new;
    }

    public function forShare(): static
    {
        $new = clone $this;
        $new->lock = 'FOR SHARE';
        return $new;
    }

    // ── BUILD ────────────────────────────────────────────────────────

    /**
     * Render the builder's state to a BuiltQuery (SQL + params).
     */
    public function build(): BuiltQuery
    {
        $sql = '';
        $params = [];

        $this->buildSelect($sql, $params);
        $this->buildFrom($sql);
        $this->buildJoins($sql, $params);
        $this->buildWhere($sql, $params);
        $this->buildGroupBy($sql);
        $this->buildHaving($sql, $params);
        $this->buildOrderBy($sql, $params);

        $sql = $this->quoter->addLimitOffset($sql, [
            'limit' => $this->limit,
            'offset' => $this->offset,
        ]);

        if ($this->lock !== null) {
            $this->quoter->addLock($sql, ['lock' => $this->lock]);
        }

        return new BuiltQuery($sql, $params);
    }

    // ── Internal rendering ───────────────────────────────────────────

    private function buildSelect(string &$sql, array &$params): void
    {
        $sql .= 'SELECT ';

        if ($this->distinct) {
            $sql .= 'DISTINCT ';
        }

        if (empty($this->columns)) {
            $sql .= '*';
            return;
        }

        $rendered = [];
        foreach ($this->columns as $col) {
            $rendered[] = $this->renderColumnExpr($col, $params);
        }
        $sql .= implode(', ', $rendered);
    }

    private function buildFrom(string &$sql): void
    {
        if ($this->table === null) {
            return;
        }

        $sql .= ' FROM ' . $this->quoter->quoteTableName($this->table->name);
        if ($this->table->alias !== null) {
            $sql .= ' ' . $this->quoter->quoteTableName($this->table->alias);
        }
    }

    private function buildJoins(string &$sql, array &$params): void
    {
        foreach ($this->joins as $join) {
            $type = $join->getType();
            $table = $join->getTable();

            $sql .= ' ' . $type . ' JOIN ';
            $sql .= $this->quoter->quoteTableName($table->name);
            if ($table->alias !== null) {
                $sql .= ' ' . $this->quoter->quoteTableName($table->alias);
            }

            $conditions = $join->getConditions();
            if (!empty($conditions)) {
                $sql .= ' ON ';
                $this->renderNodes($conditions, $sql, $params);
            }
        }
    }

    private function buildWhere(string &$sql, array &$params): void
    {
        if (empty($this->wheres)) {
            return;
        }

        $sql .= ' WHERE ';
        $this->renderNodes($this->wheres, $sql, $params);
    }

    private function buildGroupBy(string &$sql): void
    {
        if (empty($this->groupBy)) {
            return;
        }

        $quoted = array_map(
            fn(string $col) => $this->quoter->quoteColumnName($col),
            $this->groupBy,
        );
        $sql .= ' GROUP BY ' . implode(', ', $quoted);
    }

    private function buildHaving(string &$sql, array &$params): void
    {
        if (empty($this->havings)) {
            return;
        }

        $sql .= ' HAVING ';
        $this->renderNodes($this->havings, $sql, $params);
    }

    private function buildOrderBy(string &$sql, array &$params): void
    {
        if (empty($this->orderBy)) {
            return;
        }

        $parts = [];
        foreach ($this->orderBy as [$col, $dir]) {
            if ($col instanceof Expression) {
                $rendered = $col->sql;
                array_push($params, ...$col->params);
            } else {
                $rendered = $this->quoter->quoteColumnName($col);
            }
            if ($dir !== '') {
                $rendered .= ' ' . $dir;
            }
            $parts[] = $rendered;
        }
        $sql .= ' ORDER BY ' . implode(', ', $parts);
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

    /**
     * Render a column or Expression for the SELECT list.
     *
     * Handles the {{column:name}} placeholder pattern from Expr:: methods
     * and raw Expression objects.
     */
    private function renderColumnExpr(string|Expression $col, array &$params): string
    {
        if ($col instanceof Expression) {
            $rendered = $this->resolveColumnPlaceholders($col->sql);
            array_push($params, ...$col->params);
            return $rendered;
        }

        // Check for "column AS alias" pattern
        if (stripos($col, ' AS ') !== false) {
            $parts = preg_split('/\s+AS\s+/i', $col, 2);
            return $this->quoter->quoteColumnName($parts[0])
                . ' AS '
                . $this->quoter->quoteColumnName($parts[1]);
        }

        return $this->quoter->quoteColumnName($col);
    }

    /**
     * Replace {{column:name}} placeholders with quoted column names.
     */
    private function resolveColumnPlaceholders(string $sql): string
    {
        return preg_replace_callback(
            '/\{\{column:([^}]+)\}\}/',
            fn(array $m) => $this->quoter->quoteColumnName($m[1]),
            $sql,
        );
    }
}
