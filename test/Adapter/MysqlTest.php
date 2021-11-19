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

namespace Horde\Db\Test\Adapter;

use Horde\Test\TestCase;
use Horde\Db\Adapter\Mysql;
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
        return extension_loaded('mysql');
    }

    protected static function _getConnection($overrides = array())
    {
        $config = TestCase::getConfig(
            'DB_ADAPTER_MYSQL_TEST_CONFIG',
            null,
            array('host' => 'localhost',
                                                   'username' => '',
                                                   'password' => '',
                                                   'dbname' => 'test')
        );
        if (isset($config['db']['adapter']['mysql']['test']) &&
            is_array($config['db']['adapter']['mysql']['test'])) {
            $config = $config['db']['adapter']['mysql']['test'];
        } else {
            self::$_skip = true;
            self::$_reason = 'No configuration for mysql test';
            return;
        }
        $config = array_merge($config, $overrides);

        $conn = new Mysql($config);

        $cache = new Horde_Cache(new Horde_Cache_Storage_Mock());
        $conn->setCache($cache);

        return array($conn, $cache);
    }


    /*##########################################################################
    # Accessor
    ##########################################################################*/

    public function testAdapterName()
    {
        $this->assertEquals('MySQL', $this->_conn->adapterName());
    }
}
