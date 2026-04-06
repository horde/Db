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

namespace Horde\Db\Test\Adapter;

use Horde\Test\TestCase;
use Horde\Db\Adapter\CapabilityDetection;
use Horde\Db\Adapter\CapabilityDetectionTrait;
use Horde\Db\Adapter\Mysql\ServerInfo;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Test for CapabilityDetectionTrait.
 *
 * Tests the default implementation of hasCapability() and capability
 * name mapping logic.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 * @covers   \Horde\Db\Adapter\CapabilityDetectionTrait
 */
class CapabilityDetectionTraitTest extends TestCase
{
    /**
     * Test hasCapability with JSON capability.
     */
    public function testHasCapabilityJSON(): void
    {
        $adapter = new MockCapabilityAdapter(new ServerInfo('8.0.35', 80035));

        $this->assertTrue($adapter->hasCapability('json'));
        $this->assertTrue($adapter->hasCapability('JSON'));  // Case insensitive
    }

    /**
     * Test hasCapability with CTE capability.
     */
    public function testHasCapabilityCTE(): void
    {
        $mysql8 = new MockCapabilityAdapter(new ServerInfo('8.0.35', 80035));
        $this->assertTrue($mysql8->hasCapability('cte'));

        $mysql57 = new MockCapabilityAdapter(new ServerInfo('5.7.44', 50744));
        $this->assertFalse($mysql57->hasCapability('cte'));
    }

    /**
     * Test hasCapability with snake_case names.
     */
    public function testHasCapabilitySnakeCase(): void
    {
        $adapter = new MockCapabilityAdapter(new ServerInfo('8.0.35', 80035));

        $this->assertTrue($adapter->hasCapability('window_functions'));
        $this->assertTrue($adapter->hasCapability('check_constraints'));
        $this->assertTrue($adapter->hasCapability('invisible_columns'));
    }

    /**
     * Test hasCapability with kebab-case names (converted to snake_case).
     */
    public function testHasCapabilityKebabCase(): void
    {
        $adapter = new MockCapabilityAdapter(new ServerInfo('8.0.35', 80035));

        $this->assertTrue($adapter->hasCapability('window-functions'));
        $this->assertTrue($adapter->hasCapability('check-constraints'));
    }

    /**
     * Test hasCapability with camelCase names.
     */
    public function testHasCapabilityCamelCase(): void
    {
        $adapter = new MockCapabilityAdapter(new ServerInfo('8.0.35', 80035));

        $this->assertTrue($adapter->hasCapability('windowFunctions'));
        $this->assertTrue($adapter->hasCapability('checkConstraints'));
    }

    /**
     * Test hasCapability throws exception for unknown capability.
     */
    public function testHasCapabilityUnknownThrowsException(): void
    {
        $adapter = new MockCapabilityAdapter(new ServerInfo('8.0.35', 80035));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Unknown capability "unknown_feature"/');

        $adapter->hasCapability('unknown_feature');
    }

    /**
     * Test exception message includes available capabilities.
     */
    public function testExceptionIncludesAvailableCapabilities(): void
    {
        $adapter = new MockCapabilityAdapter(new ServerInfo('8.0.35', 80035));

        try {
            $adapter->hasCapability('nonexistent');
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            $this->assertStringContainsString('Available capabilities:', $message);
            $this->assertStringContainsString('json', $message);
            $this->assertStringContainsString('cte', $message);
            $this->assertStringContainsString('window_functions', $message);
        }
    }

    /**
     * Test all MySQL 8.0 capabilities are detected.
     */
    public function testAllMySQL80Capabilities(): void
    {
        $adapter = new MockCapabilityAdapter(new ServerInfo('8.0.35', 80035));

        $capabilities = [
            'json',
            'cte',
            'window_functions',
            'check_constraints',
            'invisible_columns',
            'descending_indexes',
            'instant_add_column',
            'rename_column',
            'generated_columns',
            'spatial_indexes',
        ];

        foreach ($capabilities as $capability) {
            $this->assertTrue(
                $adapter->hasCapability($capability),
                "MySQL 8.0 should support: $capability"
            );
        }
    }

    /**
     * Test MariaDB 10.11 capabilities.
     */
    public function testMariaDB1011Capabilities(): void
    {
        $adapter = new MockCapabilityAdapter(new ServerInfo('10.11.7-MariaDB', 101107));

        $this->assertTrue($adapter->hasCapability('json'));
        $this->assertTrue($adapter->hasCapability('cte'));
        $this->assertTrue($adapter->hasCapability('window_functions'));
        $this->assertTrue($adapter->hasCapability('descending_indexes'));
    }

    /**
     * Test old MySQL 5.6 lacks modern features.
     */
    public function testMySQL56LacksModernFeatures(): void
    {
        $adapter = new MockCapabilityAdapter(new ServerInfo('5.6.51', 50651));

        $this->assertFalse($adapter->hasCapability('json'));
        $this->assertFalse($adapter->hasCapability('cte'));
        $this->assertFalse($adapter->hasCapability('window_functions'));
    }

    /**
     * Test capability name normalization.
     */
    public function testCapabilityNameNormalization(): void
    {
        $adapter = new MockCapabilityAdapter(new ServerInfo('8.0.35', 80035));

        // All these should resolve to the same capability
        $this->assertTrue($adapter->hasCapability('json'));
        $this->assertTrue($adapter->hasCapability('JSON'));
        $this->assertTrue($adapter->hasCapability('Json'));
    }

    /**
     * Test getAvailableCapabilities returns all supported capabilities.
     */
    public function testGetAvailableCapabilities(): void
    {
        $adapter = new MockCapabilityAdapter(new ServerInfo('8.0.35', 80035));

        // Access protected method via reflection
        $reflection = new ReflectionClass($adapter);
        $method = $reflection->getMethod('getAvailableCapabilities');
        $method->setAccessible(true);

        $capabilities = $method->invoke($adapter, $adapter->getServerCapabilities());

        $this->assertIsArray($capabilities);
        $this->assertContains('json', $capabilities);
        $this->assertContains('cte', $capabilities);
        $this->assertContains('window_functions', $capabilities);
        $this->assertGreaterThanOrEqual(10, count($capabilities));
    }
}

/**
 * Mock adapter for testing CapabilityDetectionTrait.
 */
class MockCapabilityAdapter implements CapabilityDetection
{
    use CapabilityDetectionTrait;

    private ServerInfo $serverInfo;

    public function __construct(ServerInfo $serverInfo)
    {
        $this->serverInfo = $serverInfo;
    }

    public function getServerCapabilities(): object
    {
        return $this->serverInfo;
    }
}
