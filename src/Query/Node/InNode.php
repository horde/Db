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

namespace Horde\Db\Query\Node;

/**
 * An IN or NOT IN condition.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
final readonly class InNode extends WhereNode
{
    public function __construct(
        public string $column,
        public array $values,
        public bool $not = false,
        public string $boolean = 'AND',
    ) {}
}
