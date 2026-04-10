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

use Horde\Db\Query\Node\ColumnConditionNode;
use Horde\Db\Query\Node\ConditionNode;
use Horde\Db\Query\Node\WhereNode;

/**
 * Mutable join-condition builder used inside closures.
 *
 * Created fresh for each join closure call. Collects ON conditions.
 * `on()` treats both sides as column references (quoted, not bound).
 * `onValue()` treats the right side as a value (bound as ?).
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
class JoinClause
{
    /** @var WhereNode[] */
    private array $conditions = [];

    private Table $table;
    private string $type;

    public function __construct(Table $table, string $type = 'INNER')
    {
        $this->table = $table;
        $this->type = $type;
    }

    /**
     * Add a column-to-column ON condition.
     *
     * Both sides are quoted as column names.
     */
    public function on(string $left, string $operator = '=', ?string $right = null): static
    {
        if ($right === null) {
            $right = $operator;
            $operator = '=';
        }
        $this->conditions[] = new ColumnConditionNode($left, $operator, $right, 'AND');
        return $this;
    }

    /**
     * Add a column-to-column ON condition with OR.
     */
    public function orOn(string $left, string $operator = '=', ?string $right = null): static
    {
        if ($right === null) {
            $right = $operator;
            $operator = '=';
        }
        $this->conditions[] = new ColumnConditionNode($left, $operator, $right, 'OR');
        return $this;
    }

    /**
     * Add a column-to-value ON condition.
     *
     * The left side is a column (quoted). The right side is a value (bound as ?).
     */
    public function onValue(string $column, string $operator, mixed $value): static
    {
        $this->conditions[] = new ConditionNode($column, $operator, $value, 'AND');
        return $this;
    }

    public function getTable(): Table
    {
        return $this->table;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return WhereNode[]
     */
    public function getConditions(): array
    {
        return $this->conditions;
    }
}
