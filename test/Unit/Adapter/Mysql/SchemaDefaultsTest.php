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

use PHPUnit\Framework\TestCase;
use Horde\Db\Adapter\Mysql\Schema;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Test for MySQL Schema filterDefault() method.
 *
 * Tests the MySQL 8.0.13+ requirement that TEXT/BLOB/JSON/GEOMETRY columns
 * must have default values specified as expressions, not literals.
 *
 * Bug #15172: MySQL 8.0.13+ requires expression syntax for certain column types.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
#[CoversClass(Schema::class)]
class SchemaDefaultsTest extends TestCase
{
    /**
     * Test TEXT column default is wrapped in expression syntax.
     */
    public function testTextColumnDefaultWrapped(): void
    {
        $result = Schema::filterDefault('default value', 'TEXT');
        $this->assertEquals("('default value')", $result);
    }

    /**
     * Test TINYTEXT column default is wrapped.
     */
    public function testTinyTextColumnDefaultWrapped(): void
    {
        $result = Schema::filterDefault('tiny', 'TINYTEXT');
        $this->assertEquals("('tiny')", $result);
    }

    /**
     * Test MEDIUMTEXT column default is wrapped.
     */
    public function testMediumTextColumnDefaultWrapped(): void
    {
        $result = Schema::filterDefault('medium', 'MEDIUMTEXT');
        $this->assertEquals("('medium')", $result);
    }

    /**
     * Test LONGTEXT column default is wrapped.
     */
    public function testLongTextColumnDefaultWrapped(): void
    {
        $result = Schema::filterDefault('long', 'LONGTEXT');
        $this->assertEquals("('long')", $result);
    }

    /**
     * Test BLOB column default is wrapped.
     */
    public function testBlobColumnDefaultWrapped(): void
    {
        $result = Schema::filterDefault('blob data', 'BLOB');
        $this->assertEquals("('blob data')", $result);
    }

    /**
     * Test TINYBLOB column default is wrapped.
     */
    public function testTinyBlobColumnDefaultWrapped(): void
    {
        $result = Schema::filterDefault('tiny', 'TINYBLOB');
        $this->assertEquals("('tiny')", $result);
    }

    /**
     * Test MEDIUMBLOB column default is wrapped.
     */
    public function testMediumBlobColumnDefaultWrapped(): void
    {
        $result = Schema::filterDefault('medium', 'MEDIUMBLOB');
        $this->assertEquals("('medium')", $result);
    }

    /**
     * Test LONGBLOB column default is wrapped.
     */
    public function testLongBlobColumnDefaultWrapped(): void
    {
        $result = Schema::filterDefault('long', 'LONGBLOB');
        $this->assertEquals("('long')", $result);
    }

    /**
     * Test JSON column default is wrapped.
     */
    public function testJsonColumnDefaultWrapped(): void
    {
        $result = Schema::filterDefault('{"key":"value"}', 'JSON');
        $this->assertEquals("('{\"key\":\"value\"}')", $result);
    }

    /**
     * Test GEOMETRY column default is wrapped.
     */
    public function testGeometryColumnDefaultWrapped(): void
    {
        $result = Schema::filterDefault('POINT(0 0)', 'GEOMETRY');
        $this->assertEquals("('POINT(0 0)')", $result);
    }

    /**
     * Test already-wrapped expression is not double-wrapped.
     */
    public function testExpressionNotDoubleWrapped(): void
    {
        $result = Schema::filterDefault("('already wrapped')", 'TEXT');
        $this->assertEquals("('already wrapped')", $result);
    }

    /**
     * Test NULL default passes through unchanged.
     */
    public function testNullDefaultPassesThrough(): void
    {
        $result = Schema::filterDefault(null, 'TEXT');
        $this->assertNull($result);
    }

    /**
     * Test 'NULL' string default passes through unchanged.
     */
    public function testNullStringDefaultPassesThrough(): void
    {
        $result = Schema::filterDefault('NULL', 'TEXT');
        $this->assertEquals('NULL', $result);
    }

    /**
     * Test VARCHAR column default is not affected.
     */
    public function testVarcharDefaultUnchanged(): void
    {
        $result = Schema::filterDefault('default', 'VARCHAR');
        $this->assertEquals('default', $result);
    }

    /**
     * Test INTEGER column default is not affected.
     */
    public function testIntegerDefaultUnchanged(): void
    {
        $result = Schema::filterDefault('42', 'INTEGER');
        $this->assertEquals('42', $result);
    }

    /**
     * Test CHAR column default is not affected.
     */
    public function testCharDefaultUnchanged(): void
    {
        $result = Schema::filterDefault('X', 'CHAR');
        $this->assertEquals('X', $result);
    }

    /**
     * Test DATE column default is not affected.
     */
    public function testDateDefaultUnchanged(): void
    {
        $result = Schema::filterDefault('2026-01-01', 'DATE');
        $this->assertEquals('2026-01-01', $result);
    }

    /**
     * Test case-insensitive type matching (TEXT vs text).
     */
    public function testCaseInsensitiveTypeMatching(): void
    {
        $result1 = Schema::filterDefault('value', 'TEXT');
        $result2 = Schema::filterDefault('value', 'text');
        $this->assertEquals($result1, $result2);
    }

    /**
     * Test empty string default is wrapped.
     */
    public function testEmptyStringWrapped(): void
    {
        $result = Schema::filterDefault('', 'TEXT');
        $this->assertEquals("('')", $result);
    }

    /**
     * Test null type parameter returns default unchanged.
     */
    public function testNullTypeReturnsUnchanged(): void
    {
        $result = Schema::filterDefault('value', null);
        $this->assertEquals('value', $result);
    }

    /**
     * Test integer default value passes through unchanged.
     *
     * Bug fix: Non-string defaults should not be processed as strings.
     */
    public function testIntegerDefaultPassesThrough(): void
    {
        $result = Schema::filterDefault(42, 'INTEGER');
        $this->assertSame(42, $result);
    }

    /**
     * Test integer default for TEXT column passes through unchanged.
     *
     * Even for TEXT columns, numeric defaults should not be quoted.
     */
    public function testIntegerDefaultForTextPassesThrough(): void
    {
        $result = Schema::filterDefault(0, 'TEXT');
        $this->assertSame(0, $result);
    }

    /**
     * Test float default value passes through unchanged.
     */
    public function testFloatDefaultPassesThrough(): void
    {
        $result = Schema::filterDefault(3.14, 'DECIMAL');
        $this->assertSame(3.14, $result);
    }

    /**
     * Test boolean true default passes through unchanged.
     */
    public function testBooleanTrueDefaultPassesThrough(): void
    {
        $result = Schema::filterDefault(true, 'BOOLEAN');
        $this->assertTrue($result);
    }

    /**
     * Test boolean false default passes through unchanged.
     */
    public function testBooleanFalseDefaultPassesThrough(): void
    {
        $result = Schema::filterDefault(false, 'BOOLEAN');
        $this->assertFalse($result);
    }

    /**
     * Test zero integer default passes through (not treated as falsy).
     */
    public function testZeroIntegerDefaultPassesThrough(): void
    {
        $result = Schema::filterDefault(0, 'INTEGER');
        $this->assertSame(0, $result);
    }

    /**
     * Test negative integer default passes through unchanged.
     */
    public function testNegativeIntegerDefaultPassesThrough(): void
    {
        $result = Schema::filterDefault(-1, 'INTEGER');
        $this->assertSame(-1, $result);
    }

    /**
     * Test zero float default passes through unchanged.
     */
    public function testZeroFloatDefaultPassesThrough(): void
    {
        $result = Schema::filterDefault(0.0, 'FLOAT');
        $this->assertSame(0.0, $result);
    }
}
