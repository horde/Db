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

use LogicException;

/**
 * Immutable fluent INSERT query builder.
 *
 * Supports single-row insert via values() and multi-row insert via
 * columns() + addRow(). Each method returns a new builder instance.
 *
 * The builder produces a BuiltQuery (SQL + params) via build(). It never
 * executes SQL — that is the adapter's job.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
class InsertBuilder
{
    private ?string $table = null;

    /** @var string[] Explicit column list for multi-row insert */
    private array $columns = [];

    /**
     * Rows of values.
     *
     * For single-row insert via values(): one entry, keys are column names.
     * For multi-row insert via addRow(): positional values matching $columns.
     *
     * @var array<int, array<string, mixed>|array<int, mixed>>
     */
    private array $rows = [];

    /** @var bool Whether rows use positional (addRow) or associative (values) format */
    private bool $positional = false;

    public function __construct(
        private readonly QuotingInterface $quoter,
    ) {}

    /**
     * Set the target table.
     */
    public function into(string $table): static
    {
        $new = clone $this;
        $new->table = $table;
        return $new;
    }

    /**
     * Set column-value pairs for a single-row insert.
     *
     * Values may be scalars or Expression objects (e.g. Expr::raw('NOW()')).
     *
     * @param array<string, mixed> $values Column => value pairs.
     */
    public function values(array $values): static
    {
        $new = clone $this;
        $new->rows = [$values];
        $new->positional = false;
        return $new;
    }

    /**
     * Set explicit column names for multi-row insert.
     */
    public function columns(string ...$columns): static
    {
        $new = clone $this;
        $new->columns = array_values($columns);
        return $new;
    }

    /**
     * Add a row of positional values for multi-row insert.
     *
     * Must be used with columns() to define the column list.
     */
    public function addRow(mixed ...$values): static
    {
        $new = clone $this;
        $new->rows[] = array_values($values);
        $new->positional = true;
        return $new;
    }

    /**
     * Render the builder's state to a BuiltQuery (SQL + params).
     *
     * @throws LogicException If no table is set or no values/rows provided.
     */
    public function build(): BuiltQuery
    {
        if ($this->table === null) {
            throw new LogicException('InsertBuilder requires a table. Call into() before build().');
        }

        if (empty($this->rows)) {
            throw new LogicException('InsertBuilder requires values. Call values() or addRow() before build().');
        }

        if ($this->positional) {
            return $this->buildMultiRow();
        }

        return $this->buildSingleRow();
    }

    private function buildSingleRow(): BuiltQuery
    {
        $row = $this->rows[0];
        $columns = array_keys($row);
        $params = [];

        $quotedCols = array_map(
            fn(string $col) => $this->quoter->quoteColumnName($col),
            $columns,
        );

        $valueParts = [];
        foreach ($row as $value) {
            if ($value instanceof Expression) {
                $valueParts[] = $value->sql;
                array_push($params, ...$value->params);
            } else {
                $valueParts[] = '?';
                $params[] = $value;
            }
        }

        $sql = 'INSERT INTO ' . $this->quoter->quoteTableName($this->table)
            . ' (' . implode(', ', $quotedCols) . ')'
            . ' VALUES (' . implode(', ', $valueParts) . ')';

        return new BuiltQuery($sql, $params);
    }

    private function buildMultiRow(): BuiltQuery
    {
        if (empty($this->columns)) {
            throw new LogicException('Multi-row insert requires columns(). Call columns() before addRow().');
        }

        $params = [];

        $quotedCols = array_map(
            fn(string $col) => $this->quoter->quoteColumnName($col),
            $this->columns,
        );

        $rowParts = [];
        foreach ($this->rows as $row) {
            $valueParts = [];
            foreach ($row as $value) {
                if ($value instanceof Expression) {
                    $valueParts[] = $value->sql;
                    array_push($params, ...$value->params);
                } else {
                    $valueParts[] = '?';
                    $params[] = $value;
                }
            }
            $rowParts[] = '(' . implode(', ', $valueParts) . ')';
        }

        $sql = 'INSERT INTO ' . $this->quoter->quoteTableName($this->table)
            . ' (' . implode(', ', $quotedCols) . ')'
            . ' VALUES ' . implode(', ', $rowParts);

        return new BuiltQuery($sql, $params);
    }
}
