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

namespace Horde\Db\Adapter\Mysql;

use Horde\Db\Adapter\Base\Column as BaseColumn;
use Horde_String;

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
    /**
     * @var array
     */
    protected $hasEmptyStringDefault = array('binary', 'string', 'text');

    /**
     * @var string|null
     */
    protected $originalDefault = null;

    /**
     * Construct
     * @param   string  $name
     * @param   string|null  $default optional
     * @param   string|null  $sqlType optional
     * @param   bool $null optional
     */
    public function __construct(string $name, string $default = null, string $sqlType=null, bool $null=true)
    {
        $this->originalDefault = $default;
        parent::__construct($name, $default, $sqlType, $null);

        if ($this->isMissingDefaultForgedAsEmptyString()) {
            $this->default = null;
        }
    }

    /**
     */
    protected function setSimplifiedType()
    {
        if (strpos(Horde_String::lower($this->sqlType), 'tinyint(1)') !== false) {
            $this->type = 'boolean';
            return;
        } elseif (preg_match('/enum/i', $this->sqlType)) {
            $this->type = 'string';
            return;
        }
        parent::setSimplifiedType();
    }

    /**
     * MySQL misreports NOT NULL column default when none is given.
     * We can't detect this for columns which may have a legitimate ''
     * default (string, text, binary) but we can for others (integer,
     * datetime, boolean, and the rest).
     *
     * Test whether the column has default '', is not null, and is not
     * a type allowing default ''.
     *
     * @return  bool
     */
    protected function isMissingDefaultForgedAsEmptyString()
    {
        return !$this->null && $this->originalDefault == '' &&
            !in_array($this->type, $this->hasEmptyStringDefault);
    }
}
