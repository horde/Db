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
 * A column-to-column comparison: WHERE "left" op "right"
 *
 * Both sides are quoted as column names, not bound as values.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
final readonly class ColumnConditionNode extends WhereNode
{
    public function __construct(
        public string $left,
        public string $operator,
        public string $right,
        public string $boolean = 'AND',
    ) {}
}
