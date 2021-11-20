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
namespace Horde\Db\Adapter\Postgresql;
use \Horde\Db\Adapter;
use \Horde\Db\Adapter\Base\Column as BaseColumn;
use \Horde\Db\DbException;

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
class Column extends BaseColumn
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
     * @var int
     */
    public static $moneyPrecision = 19;


    /**
     * Construct
     * @param   string  $name
     * @param   string  $default
     * @param   string  $sqlType
     * @param   bool $null
     */
    public function __construct($name, $default, $sqlType=null, bool $null=true)
    {
        parent::__construct($name, $this->extractValueFromDefault($default), $sqlType, $null);
    }

    /**
     */
    protected function setSimplifiedType()
    {
        switch (true) {
        case preg_match('/^(?:real|double precision)$/', $this->sqlType):
            // Numeric and monetary types
            $this->type = 'float';
            return;
        case preg_match('/^money$/', $this->sqlType):
            // Monetary types
            $this->type = 'decimal';
            return;
        case preg_match('/^(?:character varying|bpchar)(?:\(\d+\))?$/', $this->sqlType):
            // Character types
            $this->type = 'string';
            return;
        case preg_match('/^bytea$/', $this->sqlType):
            // Binary data types
            $this->type = 'binary';
            return;
        case preg_match('/^timestamp with(?:out)? time zone$/', $this->sqlType):
            // Date/time types
            $this->type = 'datetime';
            return;
        case preg_match('/^interval$/', $this->sqlType):
            $this->type = 'string';
            return;
        case preg_match('/^(?:point|line|lseg|box|"?path"?|polygon|circle)$/', $this->sqlType):
            // Geometric types
            $this->type = 'string';
            return;
        case preg_match('/^(?:cidr|inet|macaddr)$/', $this->sqlType):
            // Network address types
            $this->type = 'string';
            return;
        case preg_match('/^bit(?: varying)?(?:\(\d+\))?$/', $this->sqlType):
            // Bit strings
            $this->type = 'string';
            return;
        case preg_match('/^xml$/', $this->sqlType):
            // XML type
            $this->type = 'string';
            return;
        case preg_match('/^\D+\[\]$/', $this->sqlType):
            // Arrays
            $this->type = 'string';
            return;
        case preg_match('/^oid$/', $this->sqlType):
            // Object identifier types
            $this->type = 'integer';
            return;
        }

        // Pass through all types that are not specific to PostgreSQL.
        parent::setSimplifiedType();
    }

    /**
     * Extracts the value from a PostgreSQL column default definition.
     */
    protected function extractValueFromDefault($default)
    {
        switch (true) {
        case preg_match('/\A-?\d+(\.\d*)?\z/', $default):
            // Numeric types
            return $default;
        case preg_match('/\A\'(.*)\'::(?:character varying|bpchar|text)\z/m', $default, $matches):
            // Character types
            return $matches[1];
        case preg_match('/\AE\'(.*)\'::(?:character varying|bpchar|text)\z/m', $default, $matches):
            // Character types (8.1 formatting)
            /*@TODO fix preg callback*/
            return preg_replace('/\\(\d\d\d\)/', '$1.oct.chr', $matches[1]);
        case preg_match('/\A\'(.*)\'::bytea\z/m', $default, $matches):
            // Binary data types
            return $matches[1];
        case preg_match('/\A\'(.+)\'::(?:time(?:stamp)? with(?:out)? time zone|date)\z/', $default, $matches):
            // Date/time types
            return $matches[1];
        case preg_match('/\A\'(.*)\'::interval\z/', $default, $matches):
            return $matches[1];
        case $default == 'true':
            // Boolean type
            return true;
        case $default == 'false':
            return false;
        case preg_match('/\A\'(.*)\'::(?:point|line|lseg|box|"?path"?|polygon|circle)\z/', $default, $matches):
            // Geometric types
            return $matches[1];
        case preg_match('/\A\'(.*)\'::(?:cidr|inet|macaddr)\z/', $default, $matches):
            // Network address types
            return $matches[1];
        case preg_match('/\AB\'(.*)\'::"?bit(?: varying)?"?\z/', $default, $matches):
            // Bit string types
            return $matches[1];
        case preg_match('/\A\'(.*)\'::xml\z/m', $default, $matches):
            // XML type
            return $matches[1];
        case preg_match('/\A\'(.*)\'::"?\D+"?\[\]\z/', $default, $matches):
            // Arrays
            return $matches[1];
        case preg_match('/\A-?\d+\z/', $default, $matches):
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

        return preg_replace_callback("/(?:\\\'|\\\\\\\\|\\\\\d{3})/", array($this, 'binaryToStringCallback'), $value);
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
    protected function extractLimit($sqlType)
    {
        if (preg_match('/^bigint/i', $sqlType)) {
            return 8;
        }
        if (preg_match('/^smallint/i', $sqlType)) {
            return 2;
        }
        return parent::extractLimit($sqlType);
    }

    /**
     * @param   string  $sqlType
     * @return  int
     */
    protected function extractPrecision($sqlType)
    {
        if (preg_match('/^money/', $sqlType)) {
            return self::$moneyPrecision;
        }
        return parent::extractPrecision($sqlType);
    }

    /**
     * @param   string  $sqlType
     * @return  int
     */
    protected function extractScale($sqlType)
    {
        if (preg_match('/^money/', $sqlType)) {
            return 2;
        }
        return parent::extractScale($sqlType);
    }

}
