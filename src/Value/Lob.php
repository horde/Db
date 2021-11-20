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
use \Horde\Db\Value;
/**
 * Encapsulation object for LOB values to be used in SQL statements to ensure
 * proper quoting, escaping, retrieval, etc.
 *
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @category  Horde
 * @copyright 2006-2021 Horde LLC
 * @license   http://www.horde.org/licenses/bsd
 * @package   Db
 * @property  mixed $value  The binary value as a string. @since Horde_Db 2.1.0
 * @property  resource $stream  The binary value as a stream. @since Horde_Db 2.4.0
 */
abstract class Lob implements Value
{
    /**
     * Binary scalar value to be quoted
     *
     * @var string
     */
    protected $value;

    /**
     * Binary stream value to be quoted
     *
     * @var resource
     */
    protected $stream;

    /**
     * Constructor
     *
     * @param string|resource $binaryValue  The binary value in a string or
     *                                    stream resource.
     */
    public function __construct($binaryValue)
    {
        if (is_resource($binaryValue)) {
            $this->stream = $binaryValue;
        } else {
            $this->value = $binaryValue;
        }
    }

    /**
     * Getter for $value and $stream properties.
     */
    public function __get($name)
    {
        switch ($name) {
        case 'value':
            if (isset($this->value)) {
                return $this->value;
            }
            if (isset($this->stream)) {
                rewind($this->stream);
                return stream_get_contents($this->stream);
            }
            break;

        case 'stream':
            if (isset($this->stream)) {
                return $this->stream;
            }
            if (isset($this->value)) {
                $stream = @fopen('php://temp', 'r+');
                fwrite($stream, $this->value);
                rewind($stream);
                return $stream;
            }
            break;
        }
    }

    /**
     * Setter for $value and $stream properties.
     */
    public function __set($name, $value)
    {
        switch ($name) {
        case 'value':
        case 'stream':
            $this->$name = $value;
            break;
        }
    }
}
