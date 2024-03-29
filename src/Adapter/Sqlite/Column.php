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
namespace Horde\Db\Adapter\Sqlite;
use \Horde\Db\DbException;
use \Horde\Db\Adapter\Base\Column as BaseColumn;
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
    public function extractDefault($default)
    {
        $default = parent::extractDefault($default);
        if ($this->isText()) {
            $default = $this->unquote($default);
        }
        return $default;
    }


    /*##########################################################################
    # Type Juggling
    ##########################################################################*/

    public function binaryToString($value)
    {
        return str_replace(array('%00', '%25'), array("\0", '%'), $value);
    }

    /**
     * @param   mixed  $value
     * @return  boolean
     */
    public function valueToBoolean($value)
    {
        if ($value == '"t"' || $value == "'t'") {
            return true;
        } elseif ($value == '""' || $value == "''") {
            return null;
        } else {
            return parent::valueToBoolean($value);
        }
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    /**
     * Unquote a string value
     *
     * @return string
     */
    protected function unquote($string)
    {
        $first = substr($string, 0, 1);
        if ($first == "'" || $first == '"') {
            $string = substr($string, 1);
            if (substr($string, -1) == $first) {
                $string = substr($string, 0, -1);
            }
            $string = str_replace("$first$first", $first, $string);
        }

        return $string;
    }
}
