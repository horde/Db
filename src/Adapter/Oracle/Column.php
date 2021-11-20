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
     * @param bool $null       Whether this column allows NULL values.
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
        $this->name      = $name;
        $this->sqlType   = Horde_String::lower($sqlType);
        $this->null      = $null;

        $this->limit     = $length;
        $this->precision = $precision;
        $this->scale     = $scale;

        $this->setSimplifiedType();
        $this->isText    = $this->type == 'text'  || $this->type == 'string';
        $this->isNumber  = $this->type == 'float' || $this->type == 'integer' || $this->type == 'decimal';

        $this->default   = $this->typeCast($default);
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
    protected function setSimplifiedType()
    {
        if (Horde_String::lower($this->sqlType) == 'number' &&
            $this->precision == 1) {
            $this->type = 'boolean';
            return;
        }
        parent::setSimplifiedType();
    }
}
