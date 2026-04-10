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
 * Immutable fluent DELETE query builder.
 *
 * Each method returns a new builder instance, leaving the original unchanged.
 * WHERE clause support is provided by the HasWhere trait.
 *
 * Safety: build() throws if no WHERE clause is present and
 * dangerouslyDeleteAll() has not been called. This prevents accidental
 * full-table deletes.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
class DeleteBuilder
{
    use HasWhere;

    private ?string $table = null;

    /** @var WhereNode[] */
    private array $wheres = [];

    private bool $deleteAll = false;

    public function __construct(
        private readonly QuotingInterface $quoter,
    ) {}

    /**
     * Set the target table.
     */
    public function from(string $table): static
    {
        $new = clone $this;
        $new->table = $table;
        return $new;
    }

    /**
     * Explicitly opt in to deleting all rows (no WHERE clause).
     *
     * Without calling this, build() will throw if no WHERE conditions exist.
     */
    public function dangerouslyDeleteAll(): static
    {
        $new = clone $this;
        $new->deleteAll = true;
        return $new;
    }

    /**
     * Render the builder's state to a BuiltQuery (SQL + params).
     *
     * @throws LogicException If no table is set, or no WHERE and no dangerouslyDeleteAll().
     */
    public function build(): BuiltQuery
    {
        if ($this->table === null) {
            throw new LogicException('DeleteBuilder requires a table. Call from() before build().');
        }

        if (empty($this->wheres) && !$this->deleteAll) {
            throw new LogicException(
                'DeleteBuilder requires WHERE conditions to prevent accidental full-table deletes. '
                . 'Call where() or dangerouslyDeleteAll() before build().',
            );
        }

        $sql = '';
        $params = [];

        $sql .= 'DELETE FROM ' . $this->quoter->quoteTableName($this->table);

        $this->buildWhere($sql, $params);

        return new BuiltQuery($sql, $params);
    }
}
