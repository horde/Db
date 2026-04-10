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
 * Immutable value object representing a built SQL query with bind parameters.
 *
 * Returned by all builder `build()` methods. Provides structured access
 * to the SQL string and parameter array, replacing raw tuples.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
final readonly class BuiltQuery
{
    /**
     * @param string $sql    The SQL statement with positional ? placeholders.
     * @param array  $params Bind parameter values, in order.
     */
    public function __construct(
        public string $sql,
        public array $params = [],
    ) {}

    /**
     * Return as a [sql, params] tuple for spreading into adapter methods.
     *
     * @return array{0: string, 1: array}
     */
    public function toArray(): array
    {
        return [$this->sql, $this->params];
    }
}
