<?php
/**
 * Copyright 2014-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd
 * @package  Db
 */

namespace Horde\Db\Value;

use Horde\Db\Value;
use Horde\Db\Adapter;

/**
 * Encapsulation object for long text values to be used in SQL statements to
 * ensure proper quoting, escaping, retrieval, etc.
 *
 * @author    Jan Schneider <jan@horde.org>
 * @category  Horde
 * @copyright 2014-2021 Horde LLC
 * @license   http://www.horde.org/licenses/bsd
 * @package   Db
 */
class Text extends Lob
{
    /**
     * @param Adapter $db
     */
    public function quote(Adapter $db)
    {
        return $db->quoteString($this->value);
    }
}
