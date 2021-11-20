<?php
/**
 * Copyright 2006-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   James Pepin <james@jamespepin.com>
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd
 * @package  Db
 */

namespace Horde\Db;

use Iterator;
use SplFileObject;

/**
 * Class for parsing a stream into individual SQL statements.
 *
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    James Pepin <james@jamespepin.com>
 * @category  Horde
 * @copyright 2006-2021 Horde LLC
 * @license   http://www.horde.org/licenses/bsd
 * @package   Db
 */
class StatementParser implements Iterator
{
    protected $count = 0;
    protected $currentStatement;
    protected $file;

    public function __construct($file)
    {
        if (is_string($file)) {
            $file = new SplFileObject($file, 'r');
        }
        $this->file = $file;
    }

    public function current()
    {
        if (is_null($this->currentStatement)) {
            $this->rewind();
        }
        return $this->currentStatement;
    }

    public function key()
    {
        if (is_null($this->currentStatement)) {
            $this->rewind();
        }
        return $this->count;
    }

    public function next()
    {
        if ($statement = $this->getNextStatement()) {
            $this->count++;
            return $statement;
        }
        return null;
    }

    public function rewind()
    {
        $this->count = 0;
        $this->currentStatement = null;
        $this->file->rewind();
        $this->next();
    }

    public function valid()
    {
        return !$this->file->eof() && $this->file->isReadable();
    }

    /**
     * Read the next sql statement from our file. Statements are terminated by
     * semicolons.
     *
     * @return string The next SQL statement in the file.
     */
    protected function getNextStatement()
    {
        $this->currentStatement = '';
        while (!$this->file->eof()) {
            $line = $this->file->fgets();
            if (!trim($line)) {
                continue;
            }
            if (!$this->currentStatement && substr($line, 0, 2) == '--') {
                continue;
            }

            $trimmedline = rtrim($line);
            if (substr($trimmedline, -1) == ';') {
                // Leave off the ending ;
                $this->currentStatement .= substr($trimmedline, 0, -1);
                return $this->currentStatement;
            }

            $this->currentStatement .= $line;
        }

        return $this->currentStatement;
    }
}
