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
 * A group of nested conditions (parenthesized).
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
final readonly class NestedNode extends WhereNode
{
    /**
     * @param WhereNode[] $children The child conditions.
     * @param string      $boolean  AND or OR connector.
     */
    public function __construct(
        public array $children,
        public string $boolean = 'AND',
    ) {}
}
