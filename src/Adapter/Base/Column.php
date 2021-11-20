<?php
/**
 * Copyright 2007 Maintainable Software, LLC
 * Copyright 2008-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @category   Horde
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Adapter
 */

namespace Horde\Db\Adapter\Base;

use Horde_Date;
use Horde_String;

/**
 *
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @category   Horde
 * @copyright  2007 Maintainable Software, LLC
 * @copyright  2008-2021 Horde LLC
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Adapter
 */
class Column
{
    protected $name;
    protected $type;
    protected $null;
    protected $limit;
    protected $precision;
    protected $scale;
    protected $unsigned;
    protected $default;
    protected $sqlType;
    protected $isText;
    protected $isNumber;


    /*##########################################################################
    # Construct/Destruct
    ##########################################################################*/

    /**
     * Constructor.
     *
     * @param string $name     The column's name, such as "supplier_id" in
     *                         "supplier_id int(11)".
     * @param string|null $default  The type-casted default value, such as "new" in
     *                         "sales_stage varchar(20) default 'new'".
     * @param string|null $sqlType  Used to extract the column's type, length and
     *                         signed status, if necessary. For example
     *                         "varchar" and "60" in "company_name varchar(60)"
     *                         or "unsigned => true" in "int(10) UNSIGNED".
     * @param bool $null    Whether this column allows NULL values. optional
     */
    public function __construct(string $name, string $default = null, string $sqlType = null, bool $null = true)
    {
        $this->name      = $name;
        $this->sqlType   = $sqlType;
        $this->null      = $null;

        $this->limit     = $this->extractLimit($sqlType);
        $this->precision = $this->extractPrecision($sqlType);
        $this->scale     = $this->extractScale($sqlType);
        $this->unsigned  = $this->extractUnsigned($sqlType);

        $this->setSimplifiedType();
        $this->isText    = $this->type == 'text'  || $this->type == 'string';
        $this->isNumber  = $this->type == 'float' || $this->type == 'integer' || $this->type == 'decimal';

        $this->default   = $this->extractDefault($default);
    }

    /**
     * @return  bool
     */
    public function isText()
    {
        return $this->isText;
    }

    /**
     * @return  bool
     */
    public function isNumber()
    {
        return $this->isNumber;
    }

    /**
     * Casts value (which is a String) to an appropriate instance.
     */
    public function typeCast($value)
    {
        if ($value === null) {
            return null;
        }

        switch ($this->type) {
        case 'string':
        case 'text':
            return $value;
        case 'integer':
            return strlen($value) ? (int)$value : null;
        case 'float':
            return strlen($value) ? (float)$value : null;
        case 'decimal':
            return $this->valueToDecimal($value);
        case 'datetime':
        case 'timestamp':
            return $this->stringToTime($value);
        case 'time':
            return $this->stringToDummyTime($value);
        case 'date':
            return $this->stringToDate($value);
        case 'binary':
            return $this->binaryToString($value);
        case 'boolean':
            return $this->valueToBoolean($value);
        default:
            return $value;
        }
    }

    public function extractDefault($default)
    {
        return $this->typeCast($default);
    }


    /*##########################################################################
    # Accessor
    ##########################################################################*/

    /**
     * @return  string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return  string
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @return  string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return  int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @return  int
     */
    public function precision()
    {
        return $this->precision;
    }

    /**
     * @return  int
     */
    public function scale()
    {
        return $this->scale;
    }

    /**
     * @return  bool
     */
    public function isUnsigned()
    {
        return $this->unsigned;
    }

    /**
     * @return  bool
     */
    public function isNull()
    {
        return $this->null;
    }

    /**
     * @return  string
     */
    public function getSqlType()
    {
        return $this->sqlType;
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
        return (string)$value;
    }

    /**
     * @param   string  $string
     * @return  Horde_Date
     */
    public function stringToDate($string)
    {
        if (empty($string) ||
            // preserve '0000-00-00' (http://bugs.php.net/bug.php?id=45647)
            preg_replace('/[^\d]/', '', $string) == 0) {
            return null;
        }

        $d = new Horde_Date($string);
        $d->setDefaultFormat('Y-m-d');

        return $d;
    }

    /**
     * @param   string  $string
     * @return  Horde_Date
     */
    public function stringToTime($string)
    {
        if (empty($string) ||
            // preserve '0000-00-00 00:00:00' (http://bugs.php.net/bug.php?id=45647)
            preg_replace('/[^\d]/', '', $string) == 0) {
            return null;
        }

        return new Horde_Date($string);
    }

    /**
     * @param   string  $string
     * @return  Horde_Date|null
     */
    public function stringToDummyTime(string $string)
    {
        if (empty($string)) {
            return null;
        }
        return $this->stringToTime('2000-01-01 ' . $string);
    }

    /**
     * @param   mixed  $value
     * @return  bool
     */
    public function valueToBoolean($value)
    {
        if ($value === true || $value === false) {
            return $value;
        }

        $value = Horde_String::lower($value);
        return $value == 'true' || $value == 't' || $value == '1';
    }

    /**
     * @param   mixed  $value
     * @return  float
     */
    public function valueToDecimal($value)
    {
        return (float)$value;
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    /**
     * @param   string  $sqlType
     * @return  int
     */
    protected function extractLimit($sqlType)
    {
        if (preg_match("/\((.*)\)/", $sqlType, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    /**
     * @param   string  $sqlType
     * @return  int
     */
    protected function extractPrecision($sqlType)
    {
        if (preg_match("/^(numeric|decimal|number)\((\d+)(,\d+)?\)/i", $sqlType, $matches)) {
            return (int)$matches[2];
        }
        return null;
    }

    /**
     * @param   string  $sqlType
     * @return  int
     */
    protected function extractScale($sqlType)
    {
        // What's that?
        switch (true) {
            case preg_match("/^(numeric|decimal|number)\((\d+)\)/i", $sqlType):
                return 0;
            case preg_match(
                "/^(numeric|decimal|number)\((\d+)(,(\d+))\)/i",
                $sqlType,
                $match
            ):
                return (int)$match[4];
            default:
               return 0;
         }
    }

    /**
     * @param   string  $sqlType
     * @return  int
     */
    protected function extractUnsigned($sqlType)
    {
        return (bool)preg_match('/^int.*unsigned/i', $sqlType);
    }

    /**
     */
    protected function setSimplifiedType()
    {
        switch (true) {
        case preg_match('/int/i', $this->sqlType):
            $this->type = 'integer';
            return;
        case preg_match('/float|double/i', $this->sqlType):
            $this->type = 'float';
            return;
        case preg_match('/decimal|numeric|number/i', $this->sqlType):
            $this->type = $this->scale == 0 ? 'integer' : 'decimal';
            return;
        case preg_match('/datetime/i', $this->sqlType):
            $this->type = 'datetime';
            return;
        case preg_match('/timestamp/i', $this->sqlType):
            $this->type = 'timestamp';
            return;
        case preg_match('/time/i', $this->sqlType):
            $this->type = 'time';
            return;
        case preg_match('/date/i', $this->sqlType):
            $this->type = 'date';
            return;
        case preg_match('/clob|text/i', $this->sqlType):
            $this->type = 'text';
            return;
        case preg_match('/blob|binary/i', $this->sqlType):
            $this->type = 'binary';
            return;
        case preg_match('/char|string/i', $this->sqlType):
            $this->type = 'string';
            return;
        case preg_match('/boolean/i', $this->sqlType):
            $this->type = 'boolean';
            return;
        }
    }
}
