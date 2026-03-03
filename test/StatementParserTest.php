<?php

/**
 * Copyright 2006-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * This file has been split into two separate test files:
 * - Psr0StatementParserTest.php - Tests the PSR-0 implementation (lib/)
 * - Psr4StatementParserTest.php - Tests the PSR-4 implementation (src/)
 *
 * This file is kept for backward compatibility but contains no tests.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 * @deprecated Use Psr0StatementParserTest or Psr4StatementParserTest
 */

declare(strict_types=1);

namespace Horde\Db\Test;

use Horde\Test\TestCase;

/**
 * Deprecated: This test class has been split into PSR-0 and PSR-4 specific tests.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 * @deprecated Use Psr0StatementParserTest or Psr4StatementParserTest instead
 * @coversNothing
 */
class StatementParserTest extends TestCase
{
    public function testDeprecationNotice(): void
    {
        $this->markTestSkipped(
            'This test class has been split into Psr0StatementParserTest and Psr4StatementParserTest. '
            . 'Please use those classes directly.'
        );
    }
}
