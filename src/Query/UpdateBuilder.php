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
use Horde\Db\Query\Node\ConditionNode;
use Horde\Db\Query\Node\RawNode;
use Horde\Db\Query\Node\WhereNode;
use LogicException;

/**
 * Immutable fluent UPDATE query builder.
 *
 * Each method returns a new builder instance, leaving the original unchanged.
 * WHERE clause support is provided by the HasWhere trait.
 *
 * The builder produces a BuiltQuery (SQL + params) via build(). It never
 * executes SQL — that is the adapter's job.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
class UpdateBuilder
{
    use HasWhere;

    private ?string $table = null;

    /**
     * SET assignments: column => value|Expression.
     *
     * @var array<string, mixed>
     */
    private array $sets = [];

    /** @var WhereNode[] */
    private array $wheres = [];

    public function __construct(
        private readonly QuotingInterface $quoter,
    ) {}

    /**
     * Set the target table.
     */
    public function table(string $table): static
    {
        $new = clone $this;
        $new->table = $table;
        return $new;
    }

    /**
     * Set column-value pairs to update.
     *
     * Merges with any previously set values (later calls win on key collision).
     * Values may be scalars or Expression objects (e.g. Expr::raw('NOW()')).
     *
     * @param array<string, mixed> $values Column => value pairs.
     */
    public function set(array $values): static
    {
        $new = clone $this;
        $new->sets = array_merge($new->sets, $values);
        return $new;
    }

    /**
     * Increment a numeric column.
     */
    public function increment(string $column, int $amount = 1): static
    {
        $new = clone $this;
        $quoted = $this->quoter->quoteColumnName($column);
        $new->sets[$column] = new Expression($quoted . ' + ?', [$amount]);
        return $new;
    }

    /**
     * Decrement a numeric column.
     */
    public function decrement(string $column, int $amount = 1): static
    {
        $new = clone $this;
        $quoted = $this->quoter->quoteColumnName($column);
        $new->sets[$column] = new Expression($quoted . ' - ?', [$amount]);
        return $new;
    }

    /**
     * Render the builder's state to a BuiltQuery (SQL + params).
     *
     * @throws LogicException If no table or SET values are provided.
     */
    public function build(): BuiltQuery
    {
        if ($this->table === null) {
            throw new LogicException('UpdateBuilder requires a table. Call table() before build().');
        }

        if (empty($this->sets)) {
            throw new LogicException('UpdateBuilder requires SET values. Call set() before build().');
        }

        $sql = '';
        $params = [];

        $sql .= 'UPDATE ' . $this->quoter->quoteTableName($this->table);

        $this->buildSet($sql, $params);
        $this->buildWhere($sql, $params);

        return new BuiltQuery($sql, $params);
    }

    private function buildSet(string &$sql, array &$params): void
    {
        $parts = [];
        foreach ($this->sets as $column => $value) {
            $quoted = $this->quoter->quoteColumnName($column);
            if ($value instanceof Expression) {
                $parts[] = $quoted . ' = ' . $value->sql;
                array_push($params, ...$value->params);
            } else {
                $parts[] = $quoted . ' = ?';
                $params[] = $value;
            }
        }

        $sql .= ' SET ' . implode(', ', $parts);
    }
}
