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

namespace Horde\Db\Test\Adapter\Base;

use Horde\Test\TestCase;
use Horde\Db\Adapter\Base\ColumnDefinition;
use Horde\Db\Adapter\Base\Schema;

/**
 * Test for ColumnDefinition.
 *
 * Tests column definition construction and SQL generation. This class
 * was recently fixed for type safety issues (commits 3297f96, 2378904).
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 * @covers   \Horde\Db\Adapter\Base\ColumnDefinition
 */
class ColumnDefinitionTest extends TestCase
{
    private $mockBase;

    protected function setUp(): void
    {
        // Create a mock Schema adapter
        $this->mockBase = $this->createMock(Schema::class);

        // Mock quoteColumnName to wrap in quotes
        $this->mockBase->method('quoteColumnName')
            ->willReturnCallback(fn($name) => "\"$name\"");

        // Mock typeToSql to return a simple type
        $this->mockBase->method('typeToSql')
            ->willReturn('VARCHAR(255)');

        // Mock addColumnOptions to return SQL with default
        $this->mockBase->method('addColumnOptions')
            ->willReturnCallback(function ($sql, $options) {
                if (isset($options['default']) && $options['default'] !== null) {
                    $default = $options['default'];
                    if ($default === true) {
                        $sql .= ' DEFAULT 1';
                    } elseif ($default === false) {
                        $sql .= ' DEFAULT 0';
                    } elseif (is_int($default)) {
                        $sql .= " DEFAULT $default";
                    } elseif (is_float($default)) {
                        $sql .= " DEFAULT $default";
                    } elseif ($default === null) {
                        $sql .= ' DEFAULT NULL';
                    } else {
                        $sql .= " DEFAULT '$default'";
                    }
                }
                if (isset($options['null']) && $options['null'] === false) {
                    $sql .= ' NOT NULL';
                }
                return $sql;
            });
    }

    /**
     * Test basic constructor.
     */
    public function testConstructor(): void
    {
        $col = new ColumnDefinition(
            $this->mockBase,
            'test_column',
            'string'
        );

        $this->assertEquals('test_column', $col->getName());
        $this->assertEquals('string', $col->getType());
    }

    /**
     * Test constructor with all parameters.
     */
    public function testConstructorWithAllParameters(): void
    {
        $col = new ColumnDefinition(
            $this->mockBase,
            'test_column',
            'integer',
            255,           // limit
            10,            // precision
            2,             // scale
            true,          // unsigned
            42,            // default
            false,         // null
            true           // autoincrement
        );

        $this->assertEquals('test_column', $col->getName());
        $this->assertEquals('integer', $col->getType());
        $this->assertEquals(255, $col->getLimit());
        $this->assertEquals(10, $col->precision());
        $this->assertEquals(2, $col->scale());
        $this->assertTrue($col->isUnsigned());
        $this->assertEquals(42, $col->getDefault());
        $this->assertFalse($col->isNull());
        $this->assertTrue($col->isAutoIncrement());
    }

    /**
     * Test setName.
     */
    public function testSetName(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'old_name', 'string');
        $col->setName('new_name');
        $this->assertEquals('new_name', $col->getName());
    }

    /**
     * Test setType.
     */
    public function testSetType(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'string');
        $col->setType('integer');
        $this->assertEquals('integer', $col->getType());
    }

    /**
     * Test setDefault with string value.
     */
    public function testSetDefaultWithString(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'string');
        $col->setDefault('active');
        $this->assertEquals('active', $col->getDefault());
        $this->assertIsString($col->getDefault());
    }

    /**
     * Test setDefault with null value.
     * This would have failed before commit 3297f96.
     */
    public function testSetDefaultWithNull(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'string');
        $col->setDefault(null);
        $this->assertNull($col->getDefault());
    }

    /**
     * Test setDefault with boolean true.
     */
    public function testSetDefaultWithBooleanTrue(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'boolean');
        $col->setDefault(true);
        $this->assertTrue($col->getDefault());
        $this->assertIsBool($col->getDefault());
    }

    /**
     * Test setDefault with boolean false.
     * This would have failed before commit 2378904 (false was cast to '').
     */
    public function testSetDefaultWithBooleanFalse(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'boolean');
        $col->setDefault(false);

        // Critical: false must be preserved as boolean, not cast to empty string
        $this->assertFalse($col->getDefault());
        $this->assertIsBool($col->getDefault());
        $this->assertNotSame('', $col->getDefault());
    }

    /**
     * Test setDefault with integer zero.
     */
    public function testSetDefaultWithIntegerZero(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'integer');
        $col->setDefault(0);
        $this->assertEquals(0, $col->getDefault());
        $this->assertIsInt($col->getDefault());
    }

    /**
     * Test setDefault with integer.
     */
    public function testSetDefaultWithInteger(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'integer');
        $col->setDefault(42);
        $this->assertEquals(42, $col->getDefault());
        $this->assertIsInt($col->getDefault());
    }

    /**
     * Test setDefault with float.
     */
    public function testSetDefaultWithFloat(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'float');
        $col->setDefault(3.14);
        $this->assertEquals(3.14, $col->getDefault());
        $this->assertIsFloat($col->getDefault());
    }

    /**
     * Test setDefault preserves type (no auto-casting).
     */
    public function testSetDefaultPreservesType(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'string');

        // String
        $col->setDefault('test');
        $this->assertIsString($col->getDefault());

        // Integer
        $col->setDefault(123);
        $this->assertIsInt($col->getDefault());

        // Float
        $col->setDefault(1.5);
        $this->assertIsFloat($col->getDefault());

        // Boolean true
        $col->setDefault(true);
        $this->assertIsBool($col->getDefault());
        $this->assertTrue($col->getDefault());

        // Boolean false
        $col->setDefault(false);
        $this->assertIsBool($col->getDefault());
        $this->assertFalse($col->getDefault());

        // Null
        $col->setDefault(null);
        $this->assertNull($col->getDefault());
    }

    /**
     * Test setLimit with integer.
     */
    public function testSetLimit(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'string');
        $col->setLimit(100);
        $this->assertEquals(100, $col->getLimit());
    }

    /**
     * Test setLimit with null.
     * This would have failed before commit 3297f96.
     */
    public function testSetLimitWithNull(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'string', 100);
        $col->setLimit(null);
        $this->assertNull($col->getLimit());
    }

    /**
     * Test setPrecision with integer.
     */
    public function testSetPrecision(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'decimal');
        $col->setPrecision(10);
        $this->assertEquals(10, $col->precision());
    }

    /**
     * Test setPrecision with null.
     * This would have failed before commit 3297f96.
     */
    public function testSetPrecisionWithNull(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'decimal', null, 10);
        $col->setPrecision(null);
        $this->assertNull($col->precision());
    }

    /**
     * Test setScale with integer.
     */
    public function testSetScale(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'decimal');
        $col->setScale(2);
        $this->assertEquals(2, $col->scale());
    }

    /**
     * Test setScale with null.
     * This would have failed before commit 3297f96.
     */
    public function testSetScaleWithNull(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'decimal', null, null, 2);
        $col->setScale(null);
        $this->assertNull($col->scale());
    }

    /**
     * Test setUnsigned with boolean.
     */
    public function testSetUnsigned(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'integer');
        $col->setUnsigned(true);
        $this->assertTrue($col->isUnsigned());

        $col->setUnsigned(false);
        $this->assertFalse($col->isUnsigned());
    }

    /**
     * Test setUnsigned with null.
     * This would have failed before commit 3297f96.
     */
    public function testSetUnsignedWithNull(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'integer', null, null, null, true);
        $col->setUnsigned(null);
        $this->assertNull($col->isUnsigned());
    }

    /**
     * Test setNull with boolean.
     */
    public function testSetNull(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'string');
        $col->setNull(false);
        $this->assertFalse($col->isNull());

        $col->setNull(true);
        $this->assertTrue($col->isNull());
    }

    /**
     * Test setNull with null.
     * This would have failed before commit 3297f96.
     */
    public function testSetNullWithNull(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'string', null, null, null, null, null, false);
        $col->setNull(null);
        $this->assertNull($col->isNull());
    }

    /**
     * Test setAutoIncrement with boolean.
     */
    public function testSetAutoIncrement(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'integer');
        $col->setAutoIncrement(true);
        $this->assertTrue($col->isAutoIncrement());

        $col->setAutoIncrement(false);
        $this->assertFalse($col->isAutoIncrement());
    }

    /**
     * Test setAutoIncrement with null.
     */
    public function testSetAutoIncrementWithNull(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'col', 'integer', null, null, null, null, null, null, true);
        $col->setAutoIncrement(null);
        $this->assertNull($col->isAutoIncrement());
    }

    /**
     * Test toSql basic.
     */
    public function testToSqlBasic(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'test_col', 'string');
        $sql = $col->toSql();

        $this->assertStringContainsString('"test_col"', $sql);
        $this->assertStringContainsString('VARCHAR(255)', $sql);
    }

    /**
     * Test toSql with string default.
     */
    public function testToSqlWithStringDefault(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'status', 'string');
        $col->setDefault('active');
        $sql = $col->toSql();

        $this->assertStringContainsString('"status"', $sql);
        $this->assertStringContainsString("DEFAULT 'active'", $sql);
    }

    /**
     * Test toSql with integer default.
     */
    public function testToSqlWithIntegerDefault(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'count', 'integer');
        $col->setDefault(0);
        $sql = $col->toSql();

        $this->assertStringContainsString('"count"', $sql);
        $this->assertStringContainsString('DEFAULT 0', $sql);
    }

    /**
     * Test toSql with boolean true default.
     */
    public function testToSqlWithBooleanTrueDefault(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'active', 'boolean');
        $col->setDefault(true);
        $sql = $col->toSql();

        $this->assertStringContainsString('"active"', $sql);
        $this->assertStringContainsString('DEFAULT 1', $sql);
    }

    /**
     * Test toSql with boolean false default.
     * This verifies the fix from commit 2378904.
     */
    public function testToSqlWithBooleanFalseDefault(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'archived', 'boolean');
        $col->setDefault(false);
        $sql = $col->toSql();

        $this->assertStringContainsString('"archived"', $sql);
        $this->assertStringContainsString('DEFAULT 0', $sql);
        // Must NOT contain empty string default
        $this->assertStringNotContainsString("DEFAULT ''", $sql);
    }

    /**
     * Test toSql with NOT NULL.
     */
    public function testToSqlWithNotNull(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'required_field', 'string');
        $col->setNull(false);
        $sql = $col->toSql();

        $this->assertStringContainsString('NOT NULL', $sql);
    }

    /**
     * Test toSql with float default.
     */
    public function testToSqlWithFloatDefault(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'price', 'float');
        $col->setDefault(9.99);
        $sql = $col->toSql();

        $this->assertStringContainsString('"price"', $sql);
        $this->assertStringContainsString('DEFAULT 9.99', $sql);
    }

    /**
     * Test __toString calls toSql.
     */
    public function testToString(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'test', 'string');
        $toString = (string) $col;
        $toSql = $col->toSql();

        $this->assertEquals($toSql, $toString);
    }

    /**
     * Test getSqlType.
     */
    public function testGetSqlType(): void
    {
        $col = new ColumnDefinition($this->mockBase, 'test', 'string');
        $this->assertEquals('VARCHAR(255)', $col->getSqlType());
    }
}
