<?php
/**
 * Copyright 2006-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd
 * @package  Db
 */
namespace Horde\Db\Value;
use \Horde\Db\Adapter;

/**
 * Encapsulation object for binary values to be used in SQL statements to
 * ensure proper quoting, escaping, retrieval, etc.
 *
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @category  Horde
 * @copyright 2006-2021 Horde LLC
 * @license   http://www.horde.org/licenses/bsd
 * @package   Db
 * @property  $value  The binary value as a string. @since Horde_Db 2.1.0
 * @property  $stream  The binary value as a stream. @since Horde_Db 2.4.0
 */
class Binary extends Lob
{
    /**
     * @param Adapter $db
     */
    public function quote(Adapter $db)
    {
        return $db->quoteBinary($this->value);
    }
}
