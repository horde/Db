<?php
/**
 * Copyright 2007 Maintainable Software, LLC
 * Copyright 2008-2017 Horde LLC (http://www.horde.org/)
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

/**
 *
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @category   Horde
 * @copyright  2007 Maintainable Software, LLC
 * @copyright  2008-2017 Horde LLC
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Adapter
 */
class Horde_Db_Adapter_Postgresql_Column extends Horde_Db_Adapter_Base_Column
{
    /*##########################################################################
    # Constants
    ##########################################################################*/

    /**
     * The internal PostgreSQL identifier of the money data type.
     * @const integer
     */
    const MONEY_COLUMN_TYPE_OID = 790;


    /**
     * @var integer
     */
    public static $moneyPrecision = 19;


    /**
     * Construct
     * @param   string  $name
     * @param   string  $default
     * @param   string  $sqlType
     * @param   boolean $null
     */
    public function __construct($name, $default, $sqlType=null, $null=true)
    {
        parent::__construct($name, $this->_extractValueFromDefault($default), $sqlType, $null);
    }

    /**
     */
    protected function _setSimplifiedType()
    {
        switch (true) {
        case preg_match('/^(?:real|double precision)$/', is_null($this->_sqlType) ? "" : $this->_sqlType):
            // Numeric and monetary types
            $this->_type = 'float';
            return;
        case preg_match('/^money$/', is_null($this->_sqlType) ? "" : $this->_sqlType):
            // Monetary types
            $this->_type = 'decimal';
            return;
        case preg_match('/^(?:character varying|bpchar)(?:\(\d+\))?$/', is_null($this->_sqlType) ? "" : $this->_sqlType):
            // Character types
            $this->_type = 'string';
            return;
        case preg_match('/^bytea$/', is_null($this->_sqlType) ? "" : $this->_sqlType):
            // Binary data types
            $this->_type = 'binary';
            return;
        case preg_match('/^timestamp with(?:out)? time zone$/', is_null($this->_sqlType) ? "" : $this->_sqlType):
            // Date/time types
            $this->_type = 'datetime';
            return;
        case preg_match('/^interval$/', is_null($this->_sqlType) ? "" : $this->_sqlType):
            $this->_type = 'string';
            return;
        case preg_match('/^(?:point|line|lseg|box|"?path"?|polygon|circle)$/', is_null($this->_sqlType) ? "" : $this->_sqlType):
            // Geometric types
            $this->_type = 'string';
            return;
        case preg_match('/^(?:cidr|inet|macaddr)$/', is_null($this->_sqlType) ? "" : $this->_sqlType):
            // Network address types
            $this->_type = 'string';
            return;
        case preg_match('/^bit(?: varying)?(?:\(\d+\))?$/', is_null($this->_sqlType) ? "" : $this->_sqlType):
            // Bit strings
            $this->_type = 'string';
            return;
        case preg_match('/^xml$/', is_null($this->_sqlType) ? "" : $this->_sqlType):
            // XML type
            $this->_type = 'string';
            return;
        case preg_match('/^\D+\[\]$/', is_null($this->_sqlType) ? "" : $this->_sqlType):
            // Arrays
            $this->_type = 'string';
            return;
        case preg_match('/^oid$/', is_null($this->_sqlType) ? "" : $this->_sqlType):
            // Object identifier types
            $this->_type = 'integer';
            return;
        }

        // Pass through all types that are not specific to PostgreSQL.
        parent::_setSimplifiedType();
    }

    /**
     * Extracts the value from a PostgreSQL column default definition.
     */
    protected function _extractValueFromDefault($default)
    {
        switch (true) {
        case preg_match('/\A-?\d+(\.\d*)?\z/', is_null($default) ? "" : $default):
            // Numeric types
            return $default;
        case preg_match('/\A\'(.*)\'::(?:character varying|bpchar|text)\z/m', is_null($default) ? "" : $default, $matches):
            // Character types
            return $matches[1];
        case preg_match('/\AE\'(.*)\'::(?:character varying|bpchar|text)\z/m', is_null($default) ? "" : $default, $matches):
            // Character types (8.1 formatting)
            /*@TODO fix preg callback*/
            return preg_replace('/\\(\d\d\d)/', '$1.oct.chr', $matches[1]);
        case preg_match('/\A\'(.*)\'::bytea\z/m', is_null($default) ? "" : $default, $matches):
            // Binary data types
            return $matches[1];
        case preg_match('/\A\'(.+)\'::(?:time(?:stamp)? with(?:out)? time zone|date)\z/', is_null($default) ? "" : $default, $matches):
            // Date/time types
            return $matches[1];
        case preg_match('/\A\'(.*)\'::interval\z/', is_null($default) ? "" : $default, $matches):
            return $matches[1];
        case $default == 'true':
            // Boolean type
            return true;
        case $default == 'false':
            return false;
        case preg_match('/\A\'(.*)\'::(?:point|line|lseg|box|"?path"?|polygon|circle)\z/', is_null($default) ? "" : $default, $matches):
            // Geometric types
            return $matches[1];
        case preg_match('/\A\'(.*)\'::(?:cidr|inet|macaddr)\z/', is_null($default) ? "" : $default, $matches):
            // Network address types
            return $matches[1];
        case preg_match('/\AB\'(.*)\'::"?bit(?: varying)?"?\z/', is_null($default) ? "" : $default, $matches):
            // Bit string types
            return $matches[1];
        case preg_match('/\A\'(.*)\'::xml\z/m', is_null($default) ? "" : $default, $matches):
            // XML type
            return $matches[1];
        case preg_match('/\A\'(.*)\'::"?\D+"?\[\]\z/', is_null($default) ? "" : $default, $matches):
            // Arrays
            return $matches[1];
        case preg_match('/\A-?\d+\z/', is_null($default) ? "" : $default, $matches):
            // Object identifier types
            return $matches[1];
        default:
            // Anything else is blank, some user type, or some function
            // and we can't know the value of that, so return nil.
            return null;
        }
    }

    /**
     * Used to convert from BLOBs (BYTEAs) to Strings.
     *
     * @return  string
     */
    public function binaryToString($value)
    {
        if (is_resource($value)) {
            rewind($value);
            $string = stream_get_contents($value);
            fclose($value);
            return $string;
        }

        return preg_replace_callback("/(?:\\\'|\\\\\\\\|\\\\\d{3})/", array($this, 'binaryToStringCallback'), is_null($value) ? "" : $value);
    }

    /**
     * Callback function for binaryToString().
     */
    public function binaryToStringCallback($matches)
    {
        if ($matches[0] == '\\\'') {
            return "'";
        } elseif ($matches[0] == '\\\\\\\\') {
            return '\\';
        }

        return chr(octdec(substr($matches[0], -3)));
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    /**
     * @param   string  $sqlType
     * @return  int
     */
    protected function _extractLimit($sqlType)
    {
        if (preg_match('/^bigint/i', is_null($sqlType) ? "" : $sqlType)) {
            return 8;
        }
        if (preg_match('/^smallint/i', is_null($sqlType) ? "" : $sqlType)) {
            return 2;
        }
        return parent::_extractLimit($sqlType);
    }

    /**
     * @param   string  $sqlType
     * @return  int
     */
    protected function _extractPrecision($sqlType)
    {
        if (preg_match('/^money/', is_null($sqlType) ? "" : $sqlType)) {
            return self::$moneyPrecision;
        }
        return parent::_extractPrecision($sqlType);
    }

    /**
     * @param   string  $sqlType
     * @return  int
     */
    protected function _extractScale($sqlType)
    {
        if (preg_match('/^money/', is_null($sqlType) ? "" : $sqlType)) {
            return 2;
        }
        return parent::_extractScale($sqlType);
    }

}
