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

use Horde\Db\Adapter\Base\TableDefinition as BaseTableDefinition;

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
class TableDefinition extends BaseTableDefinition
{
    protected $_createTrigger = false;

    /**
     * Adds a new column to the table definition.
     *
     * @param string $type    Column type, one of:
     *                        autoincrementKey, string, text, integer, float,
     *                        datetime, timestamp, time, date, binary, boolean.
     * @param array $options  Column options:
     *                        - limit: (integer) Maximum column length (string,
     *                          text, binary or integer columns only)
     *                        - default: (mixed) The column's default value.
     *                          You cannot explicitly set the default value to
     *                          NULL. Simply leave off this option if you want
     *                          a NULL default value.
     *                        - null: (boolean) Whether NULL values are allowed
     *                          in the column.
     *                        - precision: (integer) The number precision
     *                          (float columns only).
     *                        - scale: (integer) The number scaling (float
     *                          columns only).
     *                        - unsigned: (boolean) Whether the column is an
     *                          unsigned number (integer columns only).
     *                        - autoincrement: (boolean) Whether the column is
     *                          an autoincrement column. Restrictions are
     *                          RDMS specific.
     *
     * @return TableDefinition  This object.
     */
    public function column($name, $type, $options = [])
    {
        parent::column($name, $type, $options);

        if ($type == 'autoincrementKey') {
            $this->_createTrigger = $name;
        }

        return $this;
    }

    /**
     * Wrap up table creation block & create the table
     */
    public function end()
    {
        parent::end();
        if ($this->_createTrigger) {
            $this->_base->createAutoincrementTrigger($this->_name, $this->_createTrigger);
        }
    }
}
