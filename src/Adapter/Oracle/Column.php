<?php
/**
 * Copyright 2013-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Adapter
 */

namespace Horde\Db\Adapter\Oracle;

use Horde\Db\Adapter\Base\Column as BaseColumn;
use Horde_String;

/**
 *
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @copyright  2013-2021 Horde LLC
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @since      Horde_Db 2.1.0
 * @subpackage Adapter
 */
class Column extends BaseColumn
{
    /*##########################################################################
    # Construct/Destruct
    ##########################################################################*/

    /**
     * Constructor.
     *
     * @param string $name        Column name, such as "supplier_id" in
     *                            "supplier_id int(11)".
     * @param string $default     Type-casted default value, such as "new"
     *                            in "sales_stage varchar(20) default 'new'".
     * @param string $sqlType     Column type.
     * @param boolean $null       Whether this column allows NULL values.
     * @param integer $length     Column width.
     * @param integer $precision  Precision for NUMBER and FLOAT columns.
     * @param integer $scale      Number of digits to the right of the decimal
     *                            point in a number.
     */
    public function __construct(
        $name,
        $default,
        $sqlType = null,
        $null = true,
        $length = null,
        $precision = null,
        $scale = null
    )
    {
        $this->_name      = $name;
        $this->_sqlType   = Horde_String::lower($sqlType);
        $this->_null      = $null;

        $this->_limit     = $length;
        $this->_precision = $precision;
        $this->_scale     = $scale;

        $this->_setSimplifiedType();
        $this->_isText    = $this->_type == 'text'  || $this->_type == 'string';
        $this->_isNumber  = $this->_type == 'float' || $this->_type == 'integer' || $this->_type == 'decimal';

        $this->_default   = $this->typeCast($default);
    }


    /*##########################################################################
    # Type Juggling
    ##########################################################################*/

    /**
     * Used to convert from BLOBs to Strings
     *
     * @return  string
     */
    public function binaryToString($value)
    {
        if (is_a($value, 'OCI-Lob')) {
            return $value->load();
        }
        return parent::binaryToString($value);
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    /**
     */
    protected function _setSimplifiedType()
    {
        if (Horde_String::lower($this->_sqlType) == 'number' &&
            $this->_precision == 1) {
            $this->_type = 'boolean';
            return;
        }
        parent::_setSimplifiedType();
    }
}
