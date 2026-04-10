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
 * A column/operator/value condition: WHERE "column" op ?
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
final readonly class ConditionNode extends WhereNode
{
    public function __construct(
        public string $column,
        public string $operator,
        public mixed $value,
        public string $boolean = 'AND',
    ) {}
}
