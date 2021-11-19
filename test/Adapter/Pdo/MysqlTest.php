<?php
/**
 * Copyright 2007 Maintainable Software, LLC
 * Copyright 2008-2017 Horde LLC (http://www.horde.org/)
 *
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/bsd
 * @category   Horde
 * @package    Db
 * @subpackage UnitTests
 */

namespace Horde\Db\Test\Adapter\Pdo;

use Horde\Db\Adapter\Pdo\Mysql as MysqlAdapter;
use Horde\Db\Test\Adapter\MysqlBase;
use PDO;
use Horde\Test\TestCase;
use Horde_Cache;
use Horde_Cache_Storage_Mock;

/**
 * @author     Mike Naberezny <mike@maintainable.com>
 * @author     Derek DeVries <derek@maintainable.com>
 * @author     Chuck Hagenbuch <chuck@horde.org>
 * @author     Jan Schneider <jan@horde.org>
 * @license    http://www.horde.org/licenses/bsd
 * @group      horde_db
 * @category   Horde
 * @package    Db
 * @subpackage UnitTests
 */
class MysqlTest extends MysqlBase
{
    protected static function _available()
    {
        return extension_loaded('pdo') &&
            in_array('mysql', PDO::getAvailableDrivers());
    }

    protected static function _getConnection($overrides = array())
    {
        $config = TestCase::getConfig(
            'DB_ADAPTER_PDO_MYSQL_TEST_CONFIG',
            __DIR__ . '/..',
            array('host' => 'localhost',
                                                   'username' => '',
                                                   'password' => '',
                                                   'dbname' => 'test')
        );
        if (isset($config['db']['adapter']['pdo']['mysql']['test'])) {
            $config = $config['db']['adapter']['pdo']['mysql']['test'];
        }
        if (!is_array($config)) {
            self::$_skip = true;
            self::$_reason = 'No configuration for pdo_mysql test';
            return;
        }
        $config = array_merge($config, $overrides);

        $conn = new MysqlAdapter($config);

        $cache = new Horde_Cache(new Horde_Cache_Storage_Mock());
        $conn->setCache($cache);

        return array($conn, $cache);
    }


    /*##########################################################################
    # Accessor
    ##########################################################################*/

    public function testAdapterName()
    {
        $this->assertEquals('PDO_MySQL', $this->_conn->adapterName());
    }
}
