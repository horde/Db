<?php

/**
 * Copyright 2006-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

declare(strict_types=1);

namespace Horde\Db\Test\Adapter\Mysql;

use Horde\Test\TestCase;
use Horde\Db\Adapter\Mysql\ServerInfo;

/**
 * Test for MySQL/MariaDB ServerInfo.
 *
 * Tests server version detection and feature capability detection.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 * @covers   \Horde\Db\Adapter\Mysql\ServerInfo
 */
class ServerInfoTest extends TestCase
{
    /**
     * Test MySQL 5.7 version parsing.
     */
    public function testMysql57Parsing(): void
    {
        $info = new ServerInfo('5.7.44', 50744);

        $this->assertFalse($info->isMariaDB);
        $this->assertEquals(5, $info->majorVersion);
        $this->assertEquals(7, $info->minorVersion);
        $this->assertEquals(44, $info->patchVersion);
        $this->assertEquals('MySQL 5.7.44', $info->getDescription());
    }

    /**
     * Test MySQL 8.0 version parsing.
     */
    public function testMysql80Parsing(): void
    {
        $info = new ServerInfo('8.0.35', 80035);

        $this->assertFalse($info->isMariaDB);
        $this->assertEquals(8, $info->majorVersion);
        $this->assertEquals(0, $info->minorVersion);
        $this->assertEquals(35, $info->patchVersion);
    }

    /**
     * Test MariaDB 10.11 version parsing.
     */
    public function testMariaDB1011Parsing(): void
    {
        $info = new ServerInfo('10.11.7-MariaDB-1:10.11.7+maria~ubu2204', 101107);

        $this->assertTrue($info->isMariaDB);
        $this->assertEquals(10, $info->majorVersion);
        $this->assertEquals(11, $info->minorVersion);
        $this->assertEquals(7, $info->patchVersion);
        $this->assertEquals('MariaDB 10.11.7', $info->getDescription());
    }

    /**
     * Test MariaDB 10.5 version parsing with suffix.
     */
    public function testMariaDB105WithSuffix(): void
    {
        $info = new ServerInfo('10.5.23-MariaDB', 100523);

        $this->assertTrue($info->isMariaDB);
        $this->assertEquals(10, $info->majorVersion);
        $this->assertEquals(5, $info->minorVersion);
        $this->assertEquals(23, $info->patchVersion);
    }

    /**
     * Test isAtLeast with major version only.
     */
    public function testIsAtLeastMajor(): void
    {
        $info = new ServerInfo('8.0.35', 80035);

        $this->assertTrue($info->isAtLeast(8));
        $this->assertTrue($info->isAtLeast(7));
        $this->assertFalse($info->isAtLeast(9));
    }

    /**
     * Test isAtLeast with major and minor.
     */
    public function testIsAtLeastMinor(): void
    {
        $info = new ServerInfo('10.5.23-MariaDB', 100523);

        $this->assertTrue($info->isAtLeast(10, 5));
        $this->assertTrue($info->isAtLeast(10, 4));
        $this->assertFalse($info->isAtLeast(10, 6));
        $this->assertFalse($info->isAtLeast(11, 0));
    }

    /**
     * Test isAtLeast with major, minor, and patch.
     */
    public function testIsAtLeastPatch(): void
    {
        $info = new ServerInfo('8.0.35', 80035);

        $this->assertTrue($info->isAtLeast(8, 0, 35));
        $this->assertTrue($info->isAtLeast(8, 0, 34));
        $this->assertFalse($info->isAtLeast(8, 0, 36));
    }

    /**
     * Test JSON support detection for MySQL 5.7+.
     */
    public function testSupportsJSONMySQL57(): void
    {
        $mysql56 = new ServerInfo('5.6.51', 50651);
        $this->assertFalse($mysql56->supportsJSON());

        $mysql57 = new ServerInfo('5.7.8', 50708);
        $this->assertTrue($mysql57->supportsJSON());

        $mysql80 = new ServerInfo('8.0.35', 80035);
        $this->assertTrue($mysql80->supportsJSON());
    }

    /**
     * Test JSON support detection for MariaDB 10.2.7+.
     */
    public function testSupportsJSONMariaDB(): void
    {
        $maria102 = new ServerInfo('10.2.6-MariaDB', 100206);
        $this->assertFalse($maria102->supportsJSON());

        $maria1027 = new ServerInfo('10.2.7-MariaDB', 100207);
        $this->assertTrue($maria1027->supportsJSON());

        $maria1011 = new ServerInfo('10.11.7-MariaDB', 101107);
        $this->assertTrue($maria1011->supportsJSON());
    }

    /**
     * Test CTE support detection.
     */
    public function testSupportsCTE(): void
    {
        $mysql57 = new ServerInfo('5.7.44', 50744);
        $this->assertFalse($mysql57->supportsCTE());

        $mysql80 = new ServerInfo('8.0.35', 80035);
        $this->assertTrue($mysql80->supportsCTE());

        $maria102 = new ServerInfo('10.2.1-MariaDB', 100201);
        $this->assertTrue($maria102->supportsCTE());
    }

    /**
     * Test window functions support.
     */
    public function testSupportsWindowFunctions(): void
    {
        $mysql57 = new ServerInfo('5.7.44', 50744);
        $this->assertFalse($mysql57->supportsWindowFunctions());

        $mysql80 = new ServerInfo('8.0.35', 80035);
        $this->assertTrue($mysql80->supportsWindowFunctions());

        $maria102 = new ServerInfo('10.2.0-MariaDB', 100200);
        $this->assertTrue($maria102->supportsWindowFunctions());
    }

    /**
     * Test CHECK constraints support.
     */
    public function testSupportsCheckConstraints(): void
    {
        $mysql8015 = new ServerInfo('8.0.15', 80015);
        $this->assertFalse($mysql8015->supportsCheckConstraints());

        $mysql8016 = new ServerInfo('8.0.16', 80016);
        $this->assertTrue($mysql8016->supportsCheckConstraints());

        $maria102 = new ServerInfo('10.2.1-MariaDB', 100201);
        $this->assertTrue($maria102->supportsCheckConstraints());
    }

    /**
     * Test invisible columns support.
     */
    public function testSupportsInvisibleColumns(): void
    {
        $mysql8022 = new ServerInfo('8.0.22', 80022);
        $this->assertFalse($mysql8022->supportsInvisibleColumns());

        $mysql8023 = new ServerInfo('8.0.23', 80023);
        $this->assertTrue($mysql8023->supportsInvisibleColumns());

        $maria103 = new ServerInfo('10.3.0-MariaDB', 100300);
        $this->assertTrue($maria103->supportsInvisibleColumns());
    }

    /**
     * Test descending indexes support.
     */
    public function testSupportsDescendingIndexes(): void
    {
        $mysql57 = new ServerInfo('5.7.44', 50744);
        $this->assertFalse($mysql57->supportsDescendingIndexes());

        $mysql80 = new ServerInfo('8.0.35', 80035);
        $this->assertTrue($mysql80->supportsDescendingIndexes());

        $maria107 = new ServerInfo('10.7.0-MariaDB', 100700);
        $this->assertFalse($maria107->supportsDescendingIndexes());

        $maria108 = new ServerInfo('10.8.0-MariaDB', 100800);
        $this->assertTrue($maria108->supportsDescendingIndexes());
    }

    /**
     * Test instant ADD COLUMN support.
     */
    public function testSupportsInstantAddColumn(): void
    {
        $mysql8011 = new ServerInfo('8.0.11', 80011);
        $this->assertFalse($mysql8011->supportsInstantAddColumn());

        $mysql8012 = new ServerInfo('8.0.12', 80012);
        $this->assertTrue($mysql8012->supportsInstantAddColumn());

        $maria103 = new ServerInfo('10.3.0-MariaDB', 100300);
        $this->assertTrue($maria103->supportsInstantAddColumn());
    }

    /**
     * Test RENAME COLUMN support.
     */
    public function testSupportsRenameColumn(): void
    {
        $mysql57 = new ServerInfo('5.7.44', 50744);
        $this->assertFalse($mysql57->supportsRenameColumn());

        $mysql80 = new ServerInfo('8.0.35', 80035);
        $this->assertTrue($mysql80->supportsRenameColumn());

        $maria105 = new ServerInfo('10.5.1-MariaDB', 100501);
        $this->assertFalse($maria105->supportsRenameColumn());

        $maria1052 = new ServerInfo('10.5.2-MariaDB', 100502);
        $this->assertTrue($maria1052->supportsRenameColumn());
    }

    /**
     * Test generated columns support.
     */
    public function testSupportsGeneratedColumns(): void
    {
        $mysql56 = new ServerInfo('5.6.51', 50651);
        $this->assertFalse($mysql56->supportsGeneratedColumns());

        $mysql57 = new ServerInfo('5.7.44', 50744);
        $this->assertTrue($mysql57->supportsGeneratedColumns());

        $maria52 = new ServerInfo('5.2.0-MariaDB', 50200);
        $this->assertTrue($maria52->supportsGeneratedColumns());
    }

    /**
     * Test spatial indexes support.
     */
    public function testSupportsSpatialIndexes(): void
    {
        $mysql56 = new ServerInfo('5.6.51', 50651);
        $this->assertFalse($mysql56->supportsSpatialIndexes());

        $mysql57 = new ServerInfo('5.7.44', 50744);
        $this->assertTrue($mysql57->supportsSpatialIndexes());

        $maria1022 = new ServerInfo('10.2.2-MariaDB', 100202);
        $this->assertTrue($maria1022->supportsSpatialIndexes());
    }

    /**
     * Test version string without proper semantic version falls back to numeric.
     */
    public function testFallbackToNumericVersion(): void
    {
        // Malformed version string
        $info = new ServerInfo('unknown-version', 80035);

        $this->assertEquals(8, $info->majorVersion);
        $this->assertEquals(0, $info->minorVersion);
        $this->assertEquals(35, $info->patchVersion);
    }

    /**
     * Test all features for modern MySQL 8.0.
     */
    public function testMySQL80Features(): void
    {
        $info = new ServerInfo('8.0.35', 80035);

        $this->assertTrue($info->supportsJSON());
        $this->assertTrue($info->supportsCTE());
        $this->assertTrue($info->supportsWindowFunctions());
        $this->assertTrue($info->supportsCheckConstraints());
        $this->assertTrue($info->supportsInvisibleColumns());
        $this->assertTrue($info->supportsDescendingIndexes());
        $this->assertTrue($info->supportsInstantAddColumn());
        $this->assertTrue($info->supportsRenameColumn());
        $this->assertTrue($info->supportsGeneratedColumns());
        $this->assertTrue($info->supportsSpatialIndexes());
    }

    /**
     * Test all features for modern MariaDB 10.11.
     */
    public function testMariaDB1011Features(): void
    {
        $info = new ServerInfo('10.11.7-MariaDB', 101107);

        $this->assertTrue($info->supportsJSON());
        $this->assertTrue($info->supportsCTE());
        $this->assertTrue($info->supportsWindowFunctions());
        $this->assertTrue($info->supportsCheckConstraints());
        $this->assertTrue($info->supportsInvisibleColumns());
        $this->assertTrue($info->supportsDescendingIndexes());
        $this->assertTrue($info->supportsInstantAddColumn());
        $this->assertTrue($info->supportsRenameColumn());
        $this->assertTrue($info->supportsGeneratedColumns());
        $this->assertTrue($info->supportsSpatialIndexes());
    }
}
