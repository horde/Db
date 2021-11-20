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

use Exception;

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
class ColumnDefinition
{
    protected $base;
    protected $name;
    protected $type;
    protected $limit;
    protected $precision;
    protected $scale;
    protected $unsigned;
    protected $default;
    protected $null;
    protected $autoincrement;

    /**
     * Constructor.
     */
    public function __construct(
        $base,
        $name,
        $type,
        $limit = null,
        $precision = null,
        $scale = null,
        $unsigned = null,
        $default = null,
        $null = null,
        $autoincrement = null
    ) {
        // Protected
        $this->base      = $base;

        // Public
        $this->name          = $name;
        $this->type          = $type;
        $this->limit         = $limit;
        $this->precision     = $precision;
        $this->scale         = $scale;
        $this->unsigned      = $unsigned;
        $this->default       = $default;
        $this->null          = $null;
        $this->autoincrement = $autoincrement;
    }


    /*##########################################################################
    # Public
    ##########################################################################*/

    /**
     * @return  string
     */
    public function toSql()
    {
        $sql = $this->base->quoteColumnName($this->name) . ' ' . $this->getSqlType();
        return $this->addColumnOptions($sql, array('null'     => $this->null,
                                                    'default'  => $this->default));
    }

    /**
     * @return  string
     */
    public function __toString()
    {
        return $this->toSql();
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
     * @return  string
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * @return  string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return  string
     */
    public function getSqlType()
    {
        try {
            return $this->base->typeToSql($this->type, $this->limit, $this->precision, $this->scale, $this->unsigned);
        } catch (Exception $e) {
            return $this->type;
        }
    }

    /**
     * @return  int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @return  int
     */
    public function precision()
    {
        return $this->precision;
    }

    /**
     * @return  int
     */
    public function scale()
    {
        return $this->scale;
    }

    /**
     * @return  bool
     */
    public function isUnsigned()
    {
        return $this->unsigned;
    }

    /**
     * @return  boolean
     */
    public function isNull()
    {
        return $this->null;
    }

    /**
     * @return  bool
     */
    public function isAutoIncrement()
    {
        return $this->autoincrement;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @param string $default
     */
    public function setDefault(string $default): void
    {
        $this->default = $default;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @param int $precision
     */
    public function setPrecision($precision): void
    {
        $this->precision = $precision;
    }

    /**
     * @param int $scale
     */
    public function setScale($scale): void
    {
        $this->scale = $scale;
    }

    /**
     * @param bool $unsigned
     */
    public function setUnsigned($unsigned): void
    {
        $this->unsigned = $unsigned;
    }

    /**
     * @param bool $null
     */
    public function setNull(bool $null): void
    {
        $this->null = $null;
    }

    /**
     * @param bool $autoincrement
     */
    public function setAutoIncrement(bool $autoincrement): void
    {
        $this->autoincrement = $autoincrement;
    }


    /*##########################################################################
    # Schema Statements
    ##########################################################################*/

    /**
     * @param   string  $sql
     * @param   array   $options
     */
    protected function addColumnOptions($sql, $options)
    {
        return $this->base->addColumnOptions(
            $sql,
            array_merge($options, array('column' => $this))
        );
    }
}
