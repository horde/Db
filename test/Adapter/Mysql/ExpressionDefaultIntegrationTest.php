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

use Horde\Db\Test\Adapter\TestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Db\Adapter\Mysql\Schema;

/**
 * Integration tests for MySQL expression-based default values.
 *
 * These tests require MySQL 8.0.13+ or MariaDB 10.2.1+ where TEXT/BLOB/JSON
 * columns support default values using expression syntax.
 *
 * Tests are skipped if the database version doesn't support this feature.
 *
 * Bug #15172: MySQL 8.0.13+ requires expression syntax for certain column types.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
#[CoversClass(Schema::class)]
class ExpressionDefaultIntegrationTest extends TestBase
{
    protected static $_reason = '';
    protected static $_skip = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Skip tests if not MySQL/MariaDB
        if (!isset(self::$_adapter)) {
            self::$_skip = true;
            self::$_reason = 'Database adapter not available';
            return;
        }

        $adapterName = self::$_adapter->adapterName();
        if (!in_array($adapterName, ['MySQL', 'MySQLi', 'PDO_MySQL'])) {
            self::$_skip = true;
            self::$_reason = 'Not a MySQL adapter';
            return;
        }

        // Check MySQL version - need 8.0.13+ for TEXT/BLOB/JSON defaults
        try {
            $version = self::$_adapter->selectValue('SELECT VERSION()');
            if (preg_match('/^(\d+)\.(\d+)\.(\d+)/', $version, $matches)) {
                $major = (int)$matches[1];
                $minor = (int)$matches[2];
                $patch = (int)$matches[3];

                // MariaDB 10.2.1+ supports this
                if (stripos($version, 'mariadb') !== false) {
                    if ($major < 10 || ($major == 10 && $minor < 2)) {
                        self::$_skip = true;
                        self::$_reason = "MariaDB $version < 10.2.1 (no TEXT default support)";
                    }
                } else {
                    // MySQL 8.0.13+
                    if ($major < 8 || ($major == 8 && $minor == 0 && $patch < 13)) {
                        self::$_skip = true;
                        self::$_reason = "MySQL $version < 8.0.13 (no TEXT default support)";
                    }
                }
            }
        } catch (\Exception $e) {
            self::$_skip = true;
            self::$_reason = 'Cannot determine database version';
        }
    }

    protected function setUp(): void
    {
        if (self::$_skip) {
            $this->markTestSkipped(self::$_reason);
        }
        parent::setUp();
    }

    /**
     * Test creating TEXT column with default value.
     */
    public function testCreateTextColumnWithDefault(): void
    {
        $table = $this->getTableName('test_text_defaults');

        self::$_adapter->createTable($table, [
            'primaryKey' => 'id',
            'options' => 'ENGINE=InnoDB',
        ]);

        self::$_adapter->addColumn($table, 'bio', 'text', ['default' => 'No bio provided']);

        // Verify column exists and has default
        $columns = self::$_adapter->columns($table);
        $bioColumn = null;
        foreach ($columns as $column) {
            if ($column->getName() === 'bio') {
                $bioColumn = $column;
                break;
            }
        }

        $this->assertNotNull($bioColumn, 'bio column should exist');
        $this->assertEquals('No bio provided', $bioColumn->getDefault());

        self::$_adapter->dropTable($table);
    }

    /**
     * Test creating JSON column with default value.
     */
    public function testCreateJsonColumnWithDefault(): void
    {
        $table = $this->getTableName('test_json_defaults');

        self::$_adapter->createTable($table, [
            'primaryKey' => 'id',
            'options' => 'ENGINE=InnoDB',
        ]);

        self::$_adapter->addColumn($table, 'metadata', 'json', ['default' => '{}']);

        // Verify column exists and has default
        $columns = self::$_adapter->columns($table);
        $metadataColumn = null;
        foreach ($columns as $column) {
            if ($column->getName() === 'metadata') {
                $metadataColumn = $column;
                break;
            }
        }

        $this->assertNotNull($metadataColumn, 'metadata column should exist');
        $this->assertEquals('{}', $metadataColumn->getDefault());

        self::$_adapter->dropTable($table);
    }

    /**
     * Test creating BLOB column with default value.
     */
    public function testCreateBlobColumnWithDefault(): void
    {
        $table = $this->getTableName('test_blob_defaults');

        self::$_adapter->createTable($table, [
            'primaryKey' => 'id',
            'options' => 'ENGINE=InnoDB',
        ]);

        self::$_adapter->addColumn($table, 'data', 'blob', ['default' => 'empty']);

        // Verify column exists and has default
        $columns = self::$_adapter->columns($table);
        $dataColumn = null;
        foreach ($columns as $column) {
            if ($column->getName() === 'data') {
                $dataColumn = $column;
                break;
            }
        }

        $this->assertNotNull($dataColumn, 'data column should exist');
        $this->assertEquals('empty', $dataColumn->getDefault());

        self::$_adapter->dropTable($table);
    }

    /**
     * Test altering column to TEXT with default (changeColumn).
     */
    public function testChangeColumnToTextWithDefault(): void
    {
        $table = $this->getTableName('test_change_text');

        self::$_adapter->createTable($table, [
            'primaryKey' => 'id',
            'options' => 'ENGINE=InnoDB',
        ]);

        // Start with a VARCHAR column
        self::$_adapter->addColumn($table, 'notes', 'string', ['limit' => 255]);

        // Change to TEXT with default
        self::$_adapter->changeColumn($table, 'notes', 'text', ['default' => 'No notes']);

        // Verify column has default
        $columns = self::$_adapter->columns($table);
        $notesColumn = null;
        foreach ($columns as $column) {
            if ($column->getName() === 'notes') {
                $notesColumn = $column;
                break;
            }
        }

        $this->assertNotNull($notesColumn, 'notes column should exist');
        $this->assertEquals('No notes', $notesColumn->getDefault());

        self::$_adapter->dropTable($table);
    }

    /**
     * Test changeColumnDefault() with TEXT column.
     */
    public function testChangeColumnDefaultOnTextColumn(): void
    {
        $table = $this->getTableName('test_change_default');

        self::$_adapter->createTable($table, [
            'primaryKey' => 'id',
            'options' => 'ENGINE=InnoDB',
        ]);

        // Create TEXT column with default
        self::$_adapter->addColumn($table, 'description', 'text', ['default' => 'Original']);

        // Change the default
        self::$_adapter->changeColumnDefault($table, 'description', 'Updated');

        // Verify default changed
        $columns = self::$_adapter->columns($table);
        $descColumn = null;
        foreach ($columns as $column) {
            if ($column->getName() === 'description') {
                $descColumn = $column;
                break;
            }
        }

        $this->assertNotNull($descColumn, 'description column should exist');
        $this->assertEquals('Updated', $descColumn->getDefault());

        self::$_adapter->dropTable($table);
    }

    /**
     * Test TINYTEXT column with default.
     */
    public function testTinyTextColumnWithDefault(): void
    {
        $table = $this->getTableName('test_tinytext');

        self::$_adapter->createTable($table, [
            'primaryKey' => 'id',
            'options' => 'ENGINE=InnoDB',
        ]);

        self::$_adapter->addColumn($table, 'short_text', 'tinytext', ['default' => 'tiny']);

        $columns = self::$_adapter->columns($table);
        $column = null;
        foreach ($columns as $col) {
            if ($col->getName() === 'short_text') {
                $column = $col;
                break;
            }
        }

        $this->assertNotNull($column);
        $this->assertEquals('tiny', $column->getDefault());

        self::$_adapter->dropTable($table);
    }

    /**
     * Test MEDIUMTEXT column with default.
     */
    public function testMediumTextColumnWithDefault(): void
    {
        $table = $this->getTableName('test_mediumtext');

        self::$_adapter->createTable($table, [
            'primaryKey' => 'id',
            'options' => 'ENGINE=InnoDB',
        ]);

        self::$_adapter->addColumn($table, 'medium_text', 'mediumtext', ['default' => 'medium']);

        $columns = self::$_adapter->columns($table);
        $column = null;
        foreach ($columns as $col) {
            if ($col->getName() === 'medium_text') {
                $column = $col;
                break;
            }
        }

        $this->assertNotNull($column);
        $this->assertEquals('medium', $column->getDefault());

        self::$_adapter->dropTable($table);
    }

    /**
     * Test LONGTEXT column with default.
     */
    public function testLongTextColumnWithDefault(): void
    {
        $table = $this->getTableName('test_longtext');

        self::$_adapter->createTable($table, [
            'primaryKey' => 'id',
            'options' => 'ENGINE=InnoDB',
        ]);

        self::$_adapter->addColumn($table, 'long_text', 'longtext', ['default' => 'long']);

        $columns = self::$_adapter->columns($table);
        $column = null;
        foreach ($columns as $col) {
            if ($col->getName() === 'long_text') {
                $column = $col;
                break;
            }
        }

        $this->assertNotNull($column);
        $this->assertEquals('long', $column->getDefault());

        self::$_adapter->dropTable($table);
    }

    /**
     * Test inserting row with TEXT default value.
     */
    public function testInsertRowUsesTextDefault(): void
    {
        $table = $this->getTableName('test_insert_default');

        self::$_adapter->createTable($table, [
            'primaryKey' => 'id',
            'options' => 'ENGINE=InnoDB',
        ]);

        self::$_adapter->addColumn($table, 'bio', 'text', ['default' => 'Default bio']);
        self::$_adapter->addColumn($table, 'name', 'string', ['limit' => 50]);

        // Insert without specifying bio - should use default
        self::$_adapter->insert("INSERT INTO $table (name) VALUES (?)", ['John']);

        $row = self::$_adapter->selectOne("SELECT * FROM $table LIMIT 1");
        $this->assertEquals('Default bio', $row['bio']);

        self::$_adapter->dropTable($table);
    }

    /**
     * Test empty string as TEXT default.
     */
    public function testEmptyStringTextDefault(): void
    {
        $table = $this->getTableName('test_empty_default');

        self::$_adapter->createTable($table, [
            'primaryKey' => 'id',
            'options' => 'ENGINE=InnoDB',
        ]);

        self::$_adapter->addColumn($table, 'notes', 'text', ['default' => '']);

        $columns = self::$_adapter->columns($table);
        $notesColumn = null;
        foreach ($columns as $column) {
            if ($column->getName() === 'notes') {
                $notesColumn = $column;
                break;
            }
        }

        $this->assertNotNull($notesColumn);
        $this->assertEquals('', $notesColumn->getDefault());

        self::$_adapter->dropTable($table);
    }

    /**
     * Generate unique table name for tests.
     */
    private function getTableName(string $prefix): string
    {
        return $prefix . '_' . substr(md5((string)mt_rand()), 0, 8);
    }
}
