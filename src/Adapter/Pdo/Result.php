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

namespace Horde\Db\Adapter\Pdo;

use Horde\Db\Adapter\Base\Result as BaseResult;
use Horde\Db\Constants;
use Horde\Db\DbException;
use PDO;
use PDOException;

/**
 * This class represents the result set of a SELECT query from the PDO drivers.
 *
 * @author     Jan Schneider <jan@horde.org>
 * @category   Horde
 * @copyright  2013-2021 Horde LLC
 * @license    http://www.horde.org/licenses/bsd
 * @package    Db
 * @subpackage Adapter
 */
class Result extends BaseResult
{
    /**
     * Maps Horde_Db fetch mode constant to the extension constants.
     *
     * @var array
     */
    protected $map = array(
        Constants::FETCH_ASSOC => PDO::FETCH_ASSOC,
        Constants::FETCH_NUM   => PDO::FETCH_NUM,
        Constants::FETCH_BOTH  => PDO::FETCH_BOTH
    );

    /**
     * Returns a row from a resultset.
     *
     * @return array|boolean  The next row in the resultset or false if there
     *                        are no more results.
     */
    protected function fetchArray()
    {
        try {
            return $this->result->fetch($this->map[$this->fetchMode]);
        } catch (PDOException $e) {
            throw new DbException($e);
        }
    }

    /**
     * Returns the number of columns in the result set.
     *
     * @return int  Number of columns.
     */
    protected function _columnCount()
    {
        try {
            return $this->result->columnCount();
        } catch (PDOException $e) {
            throw new DbException($e);
        }
    }
}
