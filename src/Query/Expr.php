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

/**
 * Static factory for Expression objects.
 *
 * Provides short, composable entry points for raw SQL, aggregates,
 * and column references.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
final class Expr
{
    /** Prevent instantiation. */
    private function __construct() {}

    /**
     * Raw SQL expression.
     *
     * @param string $sql    Raw SQL fragment.
     * @param array  $params Bind parameters.
     */
    public static function raw(string $sql, array $params = []): Expression
    {
        return new Expression($sql, $params);
    }

    /**
     * Explicit column reference.
     *
     * The builder will quote the column name. Useful in set()/values()
     * where you need to reference another column instead of a literal value.
     */
    public static function column(string $name): Expression
    {
        return new Expression('{{column:' . $name . '}}');
    }

    /**
     * COUNT aggregate.
     *
     * @param string $column Column name or '*'.
     */
    public static function count(string $column = '*'): Expression
    {
        if ($column === '*') {
            return new Expression('COUNT(*)');
        }
        return new Expression('COUNT({{column:' . $column . '}})');
    }

    /**
     * COUNT(DISTINCT column) aggregate.
     */
    public static function countDistinct(string $column): Expression
    {
        return new Expression('COUNT(DISTINCT {{column:' . $column . '}})');
    }

    /**
     * SUM aggregate.
     */
    public static function sum(string $column): Expression
    {
        return new Expression('SUM({{column:' . $column . '}})');
    }

    /**
     * AVG aggregate.
     */
    public static function avg(string $column): Expression
    {
        return new Expression('AVG({{column:' . $column . '}})');
    }

    /**
     * MIN aggregate.
     */
    public static function min(string $column): Expression
    {
        return new Expression('MIN({{column:' . $column . '}})');
    }

    /**
     * MAX aggregate.
     */
    public static function max(string $column): Expression
    {
        return new Expression('MAX({{column:' . $column . '}})');
    }
}
