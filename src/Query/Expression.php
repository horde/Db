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
 * Immutable value object representing a raw SQL expression with bind parameters.
 *
 * The builder embeds the SQL literally (no quoting) and appends the params
 * to the bind array. This is the universal escape hatch — anywhere an
 * expression is expected, an Expression object works.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
final readonly class Expression
{
    /**
     * @param string $sql    Raw SQL fragment with positional ? placeholders.
     * @param array  $params Bind parameter values, in order.
     */
    public function __construct(
        public string $sql,
        public array $params = [],
    ) {}
}
