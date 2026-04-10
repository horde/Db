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
 * Immutable value object representing a table reference with optional alias.
 *
 * Used in `from()` and `join()` methods to provide type-safe table aliasing
 * without string parsing or loose arrays.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
final readonly class Table
{
    public function __construct(
        public string $name,
        public ?string $alias = null,
    ) {}

    /**
     * Named constructor for aliased tables.
     *
     * Usage: Table::as('orders', 'o')
     */
    public static function as(string $name, string $alias): self
    {
        return new self($name, $alias);
    }
}
