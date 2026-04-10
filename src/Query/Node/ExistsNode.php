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

use Horde\Db\Query\SelectBuilder;

/**
 * An EXISTS or NOT EXISTS subquery condition.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
final readonly class ExistsNode extends WhereNode
{
    public function __construct(
        public SelectBuilder $subquery,
        public bool $not = false,
        public string $boolean = 'AND',
    ) {}
}
