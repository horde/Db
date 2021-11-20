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

namespace Horde\Db\Adapter\Base;

use Horde\Db\Adapter;
use Horde\Db\Constants;
use Iterator;

/**
 * This class represents the result set of a SELECT query.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @copyright  2013-2021 Horde LLC
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Adapter
 */
abstract class Result implements Iterator
{
    /**
     * @var Adapter
     */
    protected $adapter;

    /**
     * @var string
     */
    protected $sql;

    /**
     * @var mixed
     */
    protected $arg1;

    /**
     * @var string
     */
    protected $arg2;

    /**
     * Result resource.
     *
     * @var mixed
     */
    protected $result;

    /**
     * Current row.
     *
     * @var array
     */
    protected $current;

    /**
     * Current offset.
     *
     * @var int
     */
    protected $index;

    /**
     * Are we at the end of the result?
     *
     * @var bool
     */
    protected $eof;

    /**
     * Which kind of keys to use for results.
     */
    protected $fetchMode = Constants::FETCH_ASSOC;

    /**
     * Constructor.
     *
     * @param Adapter $adapter  A driver instance.
     * @param string $sql                A SQL query.
     * @param mixed $arg1                Either an array of bound parameters or
     *                                   a query name.
     * @param string $arg2               If $arg1 contains bound parameters,
     *                                   the query name.
     */
    public function __construct($adapter, $sql, $arg1 = null, $arg2 = null)
    {
        $this->adapter = $adapter;
        $this->sql = $sql;
        $this->arg1 = $arg1;
        $this->arg2 = $arg2;
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        if ($this->result) {
            unset($this->result);
        }
    }

    /**
     * Implementation of the rewind() method for iterator.
     */
    public function rewind()
    {
        if ($this->result) {
            unset($this->result);
        }
        $this->current = null;
        $this->index = null;
        $this->eof = true;
        $this->result = $this->adapter->execute(
            $this->sql,
            $this->arg1,
            $this->arg2
        );

        $this->next();
    }

    /**
     * Implementation of the current() method for Iterator.
     *
     * @return array  The current row, or null if no rows.
     */
    public function current()
    {
        if (is_null($this->result)) {
            $this->rewind();
        }
        return $this->current;
    }

    /**
     * Implementation of the key() method for Iterator.
     *
     * @return mixed  The current row number (starts at 0), or null if no rows.
     */
    public function key()
    {
        if (is_null($this->result)) {
            $this->rewind();
        }
        return $this->index;
    }

    /**
     * Implementation of the next() method for Iterator.
     *
     * @return array|null  The next row in the resultset or null if there are
     *                     no more results.
     */
    public function next()
    {
        if (is_null($this->result)) {
            $this->rewind();
        }

        if ($this->result) {
            $row = $this->fetchArray();
            if (!$row) {
                $this->eof = true;
            } else {
                $this->eof = false;

                if (is_null($this->index)) {
                    $this->index = 0;
                } else {
                    ++$this->index;
                }

                $this->current = $row;
            }
        }

        return $this->current;
    }

    /**
     * Implementation of the valid() method for Iterator.
     *
     * @return bool  Whether the iteration is valid.
     */
    public function valid()
    {
        if (is_null($this->result)) {
            $this->rewind();
        }
        return !$this->eof;
    }

    /**
     * Returns the current row and advances the recordset one row.
     *
     * @param integer $fetchmode  The default fetch mode for this result. One
     *                            of the Constants::FETCH_* constants.
     */
    public function fetch($fetchmode = Constants::FETCH_ASSOC)
    {
        if (!$this->valid()) {
            return null;
        }
        $this->setFetchMode($fetchmode);
        $row = $this->current();
        $this->next();
        return $row;
    }

    /**
     * Sets the default fetch mode for this result.
     *
     * @param integer $fetchmode  One of the Constants::FETCH_* constants.
     */
    public function setFetchMode($fetchmode)
    {
        $this->fetchMode = $fetchmode;
    }

    /**
     * Returns the number of columns in the result set.
     *
     * @return int  Number of columns.
     */
    public function columnCount()
    {
        if (is_null($this->result)) {
            $this->rewind();
        }
        return $this->_columnCount();
    }

    /**
     * Returns a row from a resultset.
     *
     * @return array|bool  The next row in the resultset or false if there
     *                        are no more results.
     */
    abstract protected function fetchArray();

    /**
     * Returns the number of columns in the result set.
     *
     * @return int  Number of columns.
     */
    abstract protected function _columnCount();
}
