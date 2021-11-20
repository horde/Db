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

use PHPUnit\Framework\TestCase;
use Horde\Db\Adapter;
use Horde\Db\Adapter\Pdo\Sqlite;
use Horde\Db\Migration\Base as BaseMigration;
use Horde\Db\DbException;
use WeNeedReminders1;
use GiveMeBigNumbers;

require_once dirname(__DIR__) . '/fixtures/migrations/1_users_have_last_names1.php';
require_once dirname(__DIR__) . '/fixtures/migrations/2_we_need_reminders1.php';
require_once dirname(__DIR__) . '/fixtures/migrations_with_decimal/1_give_me_big_numbers.php';

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
class BaseTest extends TestCase
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

    public function testChangeColumnWithNilDefault()
    {
        $this->conn->addColumn('users', 'contributor', 'boolean', array('default' => true));
        $users = $this->conn->table('users');
        $this->assertTrue($users->contributor->getDefault());

        // changeColumn() throws exception on error
        $this->conn->changeColumn('users', 'contributor', 'boolean', array('default' => null));

        $users = $this->conn->table('users');
        $this->assertNull($users->contributor->getDefault());
    }

    public function testChangeColumnWithNewDefault()
    {
        $this->conn->addColumn('users', 'administrator', 'boolean', array('default' => true));
        $users = $this->conn->table('users');
        $this->assertTrue($users->administrator->getDefault());

        // changeColumn() throws exception on error
        $this->conn->changeColumn('users', 'administrator', 'boolean', array('default' => false));

        $users = $this->conn->table('users');
        $this->assertFalse($users->administrator->getDefault());
    }

    public function testChangeColumnDefault()
    {
        $this->conn->changeColumnDefault('users', 'first_name', 'Tester');

        $users = $this->conn->table('users');
        $this->assertEquals('Tester', $users->first_name->getDefault());
    }

    public function testChangeColumnDefaultToNull()
    {
        $this->conn->changeColumnDefault('users', 'first_name', null);

        $users = $this->conn->table('users');
        $this->assertNull($users->first_name->getDefault());
    }

    public function testAddTable()
    {
        $this->expectException(DbException::class);

        $e = null;
        $this->conn->selectValues("SELECT * FROM reminders");
        $this->assertInstanceOf('Horde_Db_Exception', $e);

        $m = new WeNeedReminders1($this->conn);
        $m->up();

        $this->conn->insert("INSERT INTO reminders (content, remind_at) VALUES ('hello world', '2005-01-01 11:10:01')");

        $reminder = (object)$this->conn->selectOne('SELECT * FROM reminders');
        $this->assertEquals('hello world', $reminder->content);

        $m->down();
        $e = null;

        $this->conn->selectValues("SELECT * FROM reminders");
        $this->assertInstanceOf('Horde_Db_Exception', $e);
    }

    public function testAddTableWithDecimals()
    {
        $this->expectException(DbException::class);

        $e = null;
        $this->conn->selectValues("SELECT * FROM big_numbers");
        $this->assertInstanceOf('Horde_Db_Exception', $e);

        $m = new GiveMeBigNumbers($this->conn);
        $m->up();

        $this->conn->insert('INSERT INTO big_numbers (bank_balance, big_bank_balance, world_population, my_house_population, value_of_e) VALUES (1586.43, 1000234000567.95, 6000000000, 3, 2.7182818284590452353602875)');

        $b = (object)$this->conn->selectOne('SELECT * FROM big_numbers');
        $this->assertNotNull($b->bank_balance);
        $this->assertNotNull($b->big_bank_balance);
        $this->assertNotNull($b->world_population);
        $this->assertNotNull($b->my_house_population);
        $this->assertNotNull($b->value_of_e);

        $m->down();
        $e = null;

        $this->conn->selectValues("SELECT * FROM big_numbers");
        $this->assertInstanceOf('Horde_Db_Exception', $e);
    }

    public function testAutoincrement()
    {
        $t = $this->conn->createTable('imp_sentmail', array('autoincrementKey' => array('sentmail_id')));
        $t->column('sentmail_id', 'bigint', array('null' => false));
        $t->column('sentmail_foo', 'string');
        $t->end();
        $migration = new BaseMigration($this->conn, null);
        $migration->changeColumn('imp_sentmail', 'sentmail_id', 'autoincrementKey');
        $columns = $this->conn->columns('imp_sentmail');
        $this->assertEquals(2, count($columns));
        $this->assertTrue(isset($columns['sentmail_id']));
        $this->assertEquals(
            array('sentmail_id'),
            $this->conn->primaryKey('imp_sentmail')->columns
        );
        $this->conn->insert('INSERT INTO imp_sentmail (sentmail_foo) VALUES (?)', array('bar'));
    }
}
