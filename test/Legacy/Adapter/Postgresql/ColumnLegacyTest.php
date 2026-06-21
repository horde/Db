<?php

/**
 * Copyright 2007-2026 Maintainable Software, LLC
 * Copyright 2008-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

declare(strict_types=1);

namespace Horde\Db\Test\Adapter\Postgresql;

use Horde_Db_Adapter_Postgresql_Column;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the legacy Horde_Db_Adapter_Postgresql_Column class from lib/.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
#[CoversClass(Horde_Db_Adapter_Postgresql_Column::class)]
class ColumnLegacyTest extends TestCase
{
    /**
     * Test that constructing with a null default does not trigger errors
     * and returns null from getDefault().
     *
     * Regression test: passing null to _extractValueFromDefault() must not
     * call preg_match() with a null subject (deprecated in PHP 8.4).
     */
    public function testMissingDefaultNull(): void
    {
        $col = new Horde_Db_Adapter_Postgresql_Column('name', null, 'integer');
        $this->assertNull($col->getDefault());
    }
}
