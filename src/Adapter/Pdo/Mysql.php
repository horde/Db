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

namespace Horde\Db\Adapter\Pdo;

use Horde\Db\Adapter\Mysql\Schema as MysqlSchema;
use Horde\Db\DbException;

/**
 * PDO_MySQL Horde_Db_Adapter
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
class Mysql extends Base
{
    /**
     * @var string
     */
    protected $schemaClass = MysqlSchema::class;

    /**
     * @return  string
     */
    public function adapterName()
    {
        return 'PDO_MySQL';
    }

    /**
     * @return  boolean
     */
    public function supportsMigrations()
    {
        return true;
    }


    /*##########################################################################
    # Connection Management
    ##########################################################################*/

    /**
     * Connect to the db
     */
    public function connect()
    {
        if ($this->active) {
            return;
        }

        parent::connect();

        // ? $this->connection->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        // Set the default charset. http://dev.mysql.com/doc/refman/5.1/en/charset-connection.html
        if (!empty($this->config['charset'])) {
            $this->schema->setCharset($this->config['charset']);
        }
    }


    /*##########################################################################
    # Protected
    ##########################################################################*/

    /**
     * Parse configuration array into options for PDO constructor.
     *
     * http://pecl.php.net/bugs/7234
     * Setting a bogus socket does not appear to work.
     *
     * @throws  DbException
     * @return  array  [dsn, username, password]
     */
    protected function parseConfig()
    {
        $this->config['adapter'] = 'mysql';

        $this->checkRequiredConfig(array('adapter', 'username'));

        if (!empty($this->config['socket'])) {
            $this->config['unix_socket'] = $this->config['socket'];
            unset($this->config['socket']);
        }

        if (!empty($this->config['host']) &&
            $this->config['host'] == 'localhost') {
            $this->config['host'] = '127.0.0.1';
        }

        // Try an empty password if it's not set.
        if (!isset($this->config['password'])) {
            $this->config['password'] = '';
        }

        // Collect options to build PDO Data Source Name (DSN) string.
        $dsnOpts = $this->config;
        unset($dsnOpts['adapter'],
              $dsnOpts['username'],
              $dsnOpts['password'],
              $dsnOpts['charset'],
              $dsnOpts['phptype']);
        $dsnOpts = $this->normalizeConfig($dsnOpts);

        if (isset($dsnOpts['port'])) {
            if (empty($dsnOpts['host'])) {
                throw new DbException('Host is required if port is specified');
            }
        }

        if (isset($dsnOpts['unix_socket'])) {
            if (!empty($dsnOpts['host']) ||
                !empty($dsnOpts['port'])) {
                throw new DbException('Host and port must not be set if using a UNIX socket');
            }
        }

        // Return DSN and user/pass for connection.
        return array(
            $this->buildDsnString($dsnOpts),
            $this->config['username'],
            $this->config['password']);
    }
}
