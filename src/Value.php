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
namespace Horde\Db;

/**
 * Interface for values with specific quoting rules.
 *
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @category  Horde
 * @copyright 2006-2021 Horde LLC
 * @license   http://www.horde.org/licenses/bsd
 * @package   Db
 */
interface Value
{
    /**
     * @param Adapter $db
     */
    public function quote(Adapter $db);
}
