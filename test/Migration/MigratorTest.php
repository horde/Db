<?php
/**
 * Copyright 2007 Maintainable Software, LLC
 * Copyright 2008-2017 Horde LLC (http://www.horde.org/)
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @license    http://www.horde.org/licenses/bsd
 * @category   Horde
 * @package    Db
 * @subpackage UnitTests
 */

namespace Horde\Db\Test\Migration;

use Horde\Test\TestCase;
use Horde\Db\Adapter;
use Horde\Db\Adapter\Pdo\Sqlite;
use Horde\Db\Migration\Migrator;
use Horde\Db\DbException;
use Exception;

/**
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @license    http://www.horde.org/licenses/bsd
 * @group      horde_db
 * @category   Horde
 * @package    Db
 * @subpackage UnitTests
 */
class MigratorTest extends TestCase
{
    protected Adapter $conn;
    public function setUp(): void
    {
        try {
            $this->conn = new Sqlite(array(
                'dbname' => ':memory:',
            ));
        } catch (DbException $e) {
            $this->markTestSkipped('The sqlite adapter is not available');
        }

        $table = $this->conn->createTable('users');
        $table->column('company_id', 'integer', array('limit' => 11));
        $table->column('name', 'string', array('limit' => 255, 'default' => ''));
        $table->column('first_name', 'string', array('limit' => 40, 'default' => ''));
        $table->column('approved', 'boolean', array('default' => true));
        $table->column('type', 'string', array('limit' => 255, 'default' => ''));
        $table->column('created_at', 'datetime', array('default' => '0000-00-00 00:00:00'));
        $table->column('created_on', 'date', array('default' => '0000-00-00'));
        $table->column('updated_at', 'datetime', array('default' => '0000-00-00 00:00:00'));
        $table->column('updated_on', 'date', array('default' => '0000-00-00'));
        $table->end();
    }

    public function testInitializeSchemaInformation()
    {
        $dir = dirname(__DIR__).'/fixtures/migrations/';
        $migrator = new Migrator($this->conn, null, array('migrationsPath' => $dir));

        $sql = "SELECT version FROM schema_info";
        $this->assertEquals(0, $this->conn->selectValue($sql));
    }

    public function testMigrator()
    {
        $this->expectException(DbException::class);

        $columns = $this->_columnNames('users');
        $this->assertFalse(in_array('last_name', $columns));

        $e = null;
        $this->conn->selectValues("SELECT * FROM reminders");
        $this->assertInstanceOf('Horde_Db_Exception', $e);

        $dir = dirname(__DIR__).'/fixtures/migrations/';
        $migrator = new Migrator($this->conn, null, array('migrationsPath' => $dir));
        $migrator->up();
        $this->assertEquals(3, $migrator->getCurrentVersion());

        $columns = $this->_columnNames('users');
        $this->assertTrue(in_array('last_name', $columns));

        $this->conn->insert("INSERT INTO reminders (content, remind_at) VALUES ('hello world', '2005-01-01 02:22:23')");
        $reminder = (object)$this->conn->selectOne('SELECT * FROM reminders');
        $this->assertEquals('hello world', $reminder->content);

        $dir = dirname(__DIR__).'/fixtures/migrations/';
        $migrator = new Migrator($this->conn, null, array('migrationsPath' => $dir));
        $migrator->down();
        $this->assertEquals(0, $migrator->getCurrentVersion());

        $columns = $this->_columnNames('users');
        $this->assertFalse(in_array('last_name', $columns));

        $e = null;
        $this->conn->selectValues("SELECT * FROM reminders");
        $this->assertInstanceOf('Horde_Db_Exception', $e);
    }

    public function testOneUp()
    {
        $this->expectException(DbException::class);

        $e = null;
        $this->conn->selectValues("SELECT * FROM reminders");
        $this->assertInstanceOf('Horde_Db_Exception', $e);

        $dir = dirname(__DIR__).'/fixtures/migrations/';
        $migrator = new Migrator($this->conn, null, array('migrationsPath' => $dir));
        $migrator->up(1);
        $this->assertEquals(1, $migrator->getCurrentVersion());

        $columns = $this->_columnNames('users');
        $this->assertTrue(in_array('last_name', $columns));

        $e = null;
        $this->conn->selectValues("SELECT * FROM reminders");
        $this->assertInstanceOf('Horde_Db_Exception', $e);

        $migrator->up(2);
        $this->assertEquals(2, $migrator->getCurrentVersion());

        $this->conn->insert("INSERT INTO reminders (content, remind_at) VALUES ('hello world', '2005-01-01 02:22:23')");
        $reminder = (object)$this->conn->selectOne('SELECT * FROM reminders');
        $this->assertEquals('hello world', $reminder->content);
    }

    public function testOneDown()
    {
        $dir = dirname(__DIR__).'/fixtures/migrations/';
        $migrator = new Migrator($this->conn, null, array('migrationsPath' => $dir));

        $migrator->up();
        $migrator->down(1);

        $columns = $this->_columnNames('users');
        $this->assertTrue(in_array('last_name', $columns));
    }

    public function testOneUpOneDown()
    {
        $dir = dirname(__DIR__).'/fixtures/migrations/';
        $migrator = new Migrator($this->conn, null, array('migrationsPath' => $dir));

        $migrator->up(1);
        $migrator->down(0);

        $columns = $this->_columnNames('users');
        $this->assertFalse(in_array('last_name', $columns));
    }

    public function testMigratorGoingDownDueToVersionTarget()
    {
        $this->expectException(DbException::class);

        $dir = dirname(__DIR__).'/fixtures/migrations/';
        $migrator = new Migrator($this->conn, null, array('migrationsPath' => $dir));

        $migrator->up(1);
        $migrator->down(0);

        $columns = $this->_columnNames('users');
        $this->assertFalse(in_array('last_name', $columns));

        $e = null;
        $this->conn->selectValues("SELECT * FROM reminders");

        $migrator = new Migrator($this->conn, null, array('migrationsPath' => $dir));
        $migrator->up();

        $columns = $this->_columnNames('users');
        $this->assertTrue(in_array('last_name', $columns));

        $this->conn->insert("INSERT INTO reminders (content, remind_at) VALUES ('hello world', '2005-01-01 02:22:23')");
        $reminder = (object)$this->conn->selectOne('SELECT * FROM reminders');
        $this->assertEquals('hello world', $reminder->content);
    }

    public function testWithDuplicates()
    {
        $this->expectException(DbException::class);
        $dir = dirname(__DIR__).'/fixtures/migrations_with_duplicate/';
        $migrator = new Migrator($this->conn, null, array('migrationsPath' => $dir));
        $migrator->up();
    }

    public function testWithMissingVersionNumbers()
    {
        $this->expectException(DbException::class);

        $dir = dirname(__DIR__).'/fixtures/migrations_with_missing_versions/';
        $migrator = new Migrator($this->conn, null, array('migrationsPath' => $dir));
        $migrator->migrate(500);
        $this->assertEquals(4, $migrator->getCurrentVersion());

        $migrator->migrate(2);
        $this->assertEquals(2, $migrator->getCurrentVersion());

        $e = null;
        $this->conn->selectValues("SELECT * FROM reminders");
        $this->assertInstanceOf('Horde_Db_Exception', $e);

        $columns = $this->_columnNames('users');
        $this->assertTrue(in_array('last_name', $columns));
    }

    protected function _columnNames($tableName)
    {
        $columns = array();
        foreach ($this->conn->columns($tableName) as $c) {
            $columns[] = $c->getName();
        }
        return $columns;
    }
}
