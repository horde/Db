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

namespace Horde\Db\Test\Adapter\Mysql;

use Horde\Db\Test\Adapter\ColumnBase as ColumnBase;
use Horde\Db\Adapter\Mysql\Column;

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
class ColumnTest extends ColumnBase
{
    protected $_class = Column::class;


    /*##########################################################################
    # Types
    ##########################################################################*/

    public function testTypeInteger()
    {
        parent::testTypeInteger();
        $col = new Column('age', 'NULL', 'int(11)');
        $this->assertFalse($col->isUnsigned());
    }

    public function testTypeIntegerUnsigned()
    {
        $col = new Column('age', 'NULL', 'int(10) UNSIGNED');
        $this->assertTrue($col->isUnsigned());
    }


    /*##########################################################################
    # Type Cast Values
    ##########################################################################*/

    public function testTypeCastBooleanFalse()
    {
        $col = new Column('is_active', '0', 'tinyint(1)', false);
        $this->assertSame(false, $col->getDefault());
    }

    public function testTypeCastBooleanTrue()
    {
        $col = new Column('is_active', '1', 'tinyint(1)', false);
        $this->assertSame(true, $col->getDefault());
    }

    /*##########################################################################
    # Column Types
    ##########################################################################*/

    public function testColumnTypeEnum()
    {
        $col = new Column('user', 'NULL', "enum('derek', 'mike')");
        $this->assertEquals('string', $col->getType());
    }

    public function testColumnTypeBoolean()
    {
        $col = new Column('is_active', 'NULL', 'tinyint(1)');
        $this->assertEquals('boolean', $col->getType());
    }
}
