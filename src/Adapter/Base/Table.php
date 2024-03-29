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
use IteratorAggregate;
use ArrayIterator;

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
class Table implements ArrayAccess, IteratorAggregate
{
    /**
     * The table's name.
     *
     * @var string
     */
    protected $name;
    protected $primaryKey;
    protected $columns;
    protected $indexes;


    /*##########################################################################
    # Construct/Destruct
    ##########################################################################*/

    /**
     * Constructor.
     *
     * @param string $name  The table's name.
     */
    public function __construct($name, $primaryKey, $columns, $indexes)
    {
        $this->name       = $name;
        $this->primaryKey = $primaryKey;
        $this->columns    = $columns;
        $this->indexes    = $indexes;
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
     * @return  mixed
     */
    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    /**
     * @return  array
     */
    public function getColumns()
    {
        return $this->columns;
    }

    /**
     * @return  Column|null
     */
    public function getColumn($column)
    {
        return isset($this->columns[$column]) ? $this->columns[$column] : null;
    }

    /**
     * @return  array
     */
    public function getColumnNames(): array
    {
        $names = [];
        foreach ($this->columns as $column) {
            $names[] = $column->getName();
        }
        return $names;
    }

    /**
     * @return  array
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * @return  array
     */
    public function getIndexNames()
    {
        $names = [];
        foreach ($this->indexes as $index) {
            $names[] = $index->getName();
        }
        return $names;
    }


    /*##########################################################################
    # Object composition
    ##########################################################################*/

    public function __get($key)
    {
        return $this->getColumn($key);
    }

    public function __isset($key)
    {
        return isset($this->columns[$key]);
    }


    /*##########################################################################
    # ArrayAccess
    ##########################################################################*/

    /**
     * ArrayAccess: Check if the given offset exists
     *
     * @param   int     $offset
     * @return  boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->columns[$offset]);
    }

    /**
     * ArrayAccess: Return the value for the given offset.
     *
     * @param   int     $offset
     * @return  object  {@link {@Horde_Db_Adapter_Base_ColumnDefinition}
     */
    public function offsetGet($offset)
    {
        return $this->getColumn($offset);
    }

    /**
     * ArrayAccess: Set value for given offset
     *
     * @param   int     $offset
     * @param   mixed   $value
     */
    public function offsetSet($offset, $value)
    {
    }

    /**
     * ArrayAccess: remove element
     *
     * @param   int     $offset
     */
    public function offsetUnset($offset)
    {
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
}
