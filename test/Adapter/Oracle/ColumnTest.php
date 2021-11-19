<?php
/**
 * Copyright 2013-2017 Horde LLC (http://www.horde.org/)
 *
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/bsd
 * @category   Horde
 * @package    Db
 * @subpackage UnitTests
 */

namespace Horde\Db\Test\Adapter\Oracle;

use Horde\Db\Test\Adapter\ColumnBase as ColumnBase;
use Horde\Db\Adapter\Oracle\Column;

/**
 * @author     Jan Schneider <jan@horde.org>
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

    public function testTypeDecimal()
    {
        $col = new $this->_class('age', 'NULL', 'decimal', true, 11, 11, 1);
        $this->assertEquals('decimal', $col->getType());
    }


    /*##########################################################################
    # Extract Limit
    ##########################################################################*/

    public function testExtractLimitInt()
    {
        $col = new $this->_class('test', 'NULL', 'int', true, 11);
        $this->assertEquals(11, $col->getLimit());
    }

    public function testExtractLimitVarchar()
    {
        $col = new $this->_class('test', 'NULL', 'varchar', true, 255);
        $this->assertEquals(255, $col->getLimit());
    }

    public function testExtractLimitDecimal()
    {
        $col = new $this->_class('test', 'NULL', 'decimal', true, 11, 11, 1);
        $this->assertEquals('11', $col->getLimit());
    }


    /*##########################################################################
    # Extract Precision/Scale
    ##########################################################################*/

    public function testExtractPrecisionScale()
    {
        $col = new $this->_class('test', 'NULL', 'decimal', true, 12, 12, 1);
        $this->assertEquals('12', $col->precision());
        $this->assertEquals('1', $col->scale());
    }


    /*##########################################################################
    # Type Cast Values
    ##########################################################################*/

    public function testTypeCastBooleanFalse()
    {
        $col = new $this->_class('is_active', '0', 'number', false, null, 1);
        $this->assertSame(false, $col->getDefault());
    }

    public function testTypeCastBooleanTrue()
    {
        $col = new $this->_class('is_active', '1', 'number', false, null, 1);
        $this->assertSame(true, $col->getDefault());
    }
}
