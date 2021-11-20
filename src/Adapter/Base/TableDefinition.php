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

use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use IteratorAggregate;
use LogicException;
use Horde\Db\DbException;

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
class TableDefinition implements ArrayAccess, IteratorAggregate
{
    protected $name    = null;
    protected $base    = null;
    protected array $options = [];
    protected $columns = null;
    protected $primaryKey = null;

    protected $columntypes = array('string', 'text', 'integer', 'float',
        'datetime', 'timestamp', 'time', 'date', 'binary', 'boolean');

    /**
     * Constructor.
     *
     * @param string $name
     * @param Schema $base
     * @param array $options
     */
    public function __construct($name, $base, array $options = [])
    {
        $this->name    = $name;
        $this->base    = $base;
        $this->options = $options;
        $this->columns = [];
    }

    /**
     * @return  string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return  array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @param   string  $name
     */
    public function primaryKey($name)
    {
        if (is_scalar($name) && $name !== false) {
            $this->column($name, 'autoincrementKey');
        }

        $this->primaryKey = $name;
    }

    /**
     * Adds a new column to the table definition.
     *
     * Examples:
     * <code>
     * // Assuming $def is an instance of Horde_Db_Adapter_Base_TableDefinition
     *
     * $def->column('granted', 'boolean');
     * // => granted BOOLEAN
     *
     * $def->column('picture', 'binary', 'limit' => 4096);
     * // => picture BLOB(4096)
     *
     * $def->column('sales_stage', 'string', array('limit' => 20, 'default' => 'new', 'null' => false));
     * // => sales_stage VARCHAR(20) DEFAULT 'new' NOT NULL
     * </code>
     *
     * @param string $type    Column type, one of:
     *                        autoincrementKey, string, text, integer, float,
     *                        datetime, timestamp, time, date, binary, boolean.
     * @param array $options  Column options:
     *                        - autoincrement: (boolean) Whether the column is
     *                          an autoincrement column. Restrictions are
     *                          RDMS specific.
     *                        - default: (mixed) The column's default value.
     *                          You cannot explicitly set the default value to
     *                          NULL. Simply leave off this option if you want
     *                          a NULL default value.
     *                        - limit: (integer) Maximum column length (string,
     *                          text, binary or integer columns only)
     *                        - null: (boolean) Whether NULL values are allowed
     *                          in the column.
     *                        - precision: (integer) The number precision
     *                          (float columns only).
     *                        - scale: (integer) The number scaling (float
     *                          columns only).
     *                        - unsigned: (boolean) Whether the column is an
     *                          unsigned number (integer columns only).
     *
     * @return TableDefinition  This object.
     */
    public function column($name, $type, $options = [])
    {
        if ($name == $this->primaryKey) {
            throw new LogicException($name . ' has already been added as a primary key');
        }

        $options = array_merge(
            array('limit'         => null,
                  'precision'     => null,
                  'scale'         => null,
                  'unsigned'      => null,
                  'default'       => null,
                  'null'          => null,
                  'autoincrement' => null),
            $options
        );

        $column = $this->base->makeColumnDefinition(
            $this->base,
            $name,
            $type,
            $options['limit'],
            $options['precision'],
            $options['scale'],
            $options['unsigned'],
            $options['default'],
            $options['null'],
            $options['autoincrement']
        );

        $this[$name] ? $this[$name] = $column : $this->columns[] = $column;

        return $this;
    }

    /**
     * Adds created_at and updated_at columns to the table.
     */
    public function timestamps()
    {
        return $this->column('created_at', 'datetime')
                    ->column('updated_at', 'datetime');
    }

    /**
     * Add one or several references to foreign keys
     *
     * This method returns self.
     */
    public function belongsTo($columns)
    {
        if (!is_array($columns)) {
            $columns = array($columns);
        }
        foreach ($columns as $col) {
            $this->column($col . '_id', 'integer');
        }

        return $this;
    }

    /**
     * Alias for the belongsTo() method
     *
     * This method returns self.
     */
    public function references($columns)
    {
        return $this->belongsTo($columns);
    }

    /**
     * Use __call to provide shorthand column creation ($this->integer(), etc.)
     */
    public function __call($method, $arguments)
    {
        if (!in_array($method, $this->columntypes)) {
            throw new BadMethodCallException('Call to undeclared method "' . $method . '"');
        }
        if (count($arguments) > 0 && count($arguments) < 3) {
            return $this->column(
                $arguments[0],
                $method,
                isset($arguments[1]) ? $arguments[1] : array()
            );
        }
        throw new BadMethodCallException('Method "'.$method.'" takes two arguments');
    }

    /**
     * Wrap up table creation block & create the table
     */
    public function end()
    {
        $this->base->endTable($this);
    }

    /**
     * Returns a String whose contents are the column definitions
     * concatenated together.  This string can then be pre and appended to
     * to generate the final SQL to create the table.
     *
     * @return  string
     */
    public function toSql()
    {
        $cols = [];
        foreach ($this->columns as $col) {
            $cols[] = $col->toSql();
        }
        $sql = '  ' . implode(", \n  ", $cols);

        // Specify composite primary keys as well
        if (is_array($this->primaryKey)) {
            $pk = [];
            foreach ($this->primaryKey as $pkColumn) {
                $pk[] = $this->base->quoteColumnName($pkColumn);
            }
            $sql .= ", \n  PRIMARY KEY(" . implode(', ', $pk) . ')';
        }

        return $sql;
    }

    public function __toString()
    {
        return $this->toSql();
    }


    /*##########################################################################
    # ArrayAccess
    ##########################################################################*/

    /**
     * ArrayAccess: Check if the given offset exists
     *
     * @param   int     $offset
     * @return  bool
     */
    public function offsetExists($offset)
    {
        foreach ($this->columns as $column) {
            if ($column->getName() == $offset) {
                return true;
            }
        }
        return false;
    }

    /**
     * ArrayAccess: Return the value for the given offset.
     *
     * @param   int     $offset
     * @return  object  {@link {@Horde_Db_Adapter_Base_ColumnDefinition}
     */
    public function offsetGet($offset)
    {
        if (!$this->offsetExists($offset)) {
            return null;
        }
        foreach ($this->columns as $column) {
            if ($column->getName() == $offset) {
                return $column;
            }
        }
        // Should never be reached
        throw new DbException('Reached situation where offsetExists reports true for a column but no column of that name is found in the list: ' . $offset);
    }

    /**
     * ArrayAccess: Set value for given offset
     *
     * @param   int     $offset
     * @param   mixed   $value
     */
    public function offsetSet($offset, $value)
    {
        foreach ($this->columns as $key=>$column) {
            if ($column->getName() == $offset) {
                $this->columns[$key] = $value;
            }
        }
    }

    /**
     * ArrayAccess: remove element
     *
     * @param   int     $offset
     */
    public function offsetUnset($offset)
    {
        foreach ($this->columns as $key=>$column) {
            if ($column->getName() == $offset) {
                unset($this->columns[$key]);
            }
        }
    }


    /*##########################################################################
    # IteratorAggregate
    ##########################################################################*/

    public function getIterator()
    {
        return new ArrayIterator($this->columns);
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    /**
     * Get the types
     */
    protected function native()
    {
        return $this->base->nativeDatabaseTypes();
    }
}
