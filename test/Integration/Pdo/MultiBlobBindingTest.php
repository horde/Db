<?php

declare(strict_types=1);

/**
 * Regression tests for multi-column LOB binding in PDO adapters.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

namespace Horde\Db\Test\Integration\Pdo;

use Horde_Db_Adapter_Pdo_Sqlite;
use Horde_Db_Value_Binary;
use Horde\Db\Test\Integration\DatabaseTestCase;

/**
 * @covers Horde_Db_Adapter_Pdo_Base::_executePrepared
 */
class MultiBlobBindingTest extends DatabaseTestCase
{
    private $conn;

    protected function setUp(): void
    {
        $this->conn = new Horde_Db_Adapter_Pdo_Sqlite($this->getSqliteConfig());
        $this->conn->execute(
            'CREATE TABLE activesync_state (
                sync_key TEXT PRIMARY KEY,
                sync_data BLOB NOT NULL,
                sync_pending TEXT,
                sync_mod INTEGER
            )'
        );
        $this->conn->insertBlob(
            'activesync_state',
            [
                'sync_key' => '{test}1',
                'sync_data' => new Horde_Db_Value_Binary('initial-folder'),
                'sync_pending' => '',
                'sync_mod' => 0,
            ],
            'sync_key',
            '{test}1'
        );
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            $this->conn->disconnect();
        }
    }

    public function testUpdateBlobWithTwoBinaryColumnsPreservesBothPayloads()
    {
        $folder = str_repeat('F', 190);
        $pending = serialize([['id' => 1, 'type' => 1]]);

        $this->conn->updateBlob(
            'activesync_state',
            [
                'sync_key' => '{test}1',
                'sync_data' => new Horde_Db_Value_Binary($folder),
                'sync_pending' => new Horde_Db_Value_Binary($pending),
                'sync_mod' => 1,
            ],
            ['sync_key = ?', ['{test}1']]
        );

        $row = $this->conn->selectOne(
            'SELECT sync_data, sync_pending, sync_mod FROM activesync_state WHERE sync_key = ?',
            ['{test}1']
        );

        $this->assertSame($folder, $row['sync_data']);
        $this->assertSame($pending, $row['sync_pending']);
        $this->assertSame(1, (int) $row['sync_mod']);
    }

    public function testUpdateBlobMaterializesExhaustedBinaryStream()
    {
        $folder = str_repeat('G', 200);
        $pending = serialize([['id' => 99]]);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $pending);
        stream_get_contents($stream);

        $this->conn->updateBlob(
            'activesync_state',
            [
                'sync_key' => '{test}1',
                'sync_data' => new Horde_Db_Value_Binary($folder),
                'sync_pending' => new Horde_Db_Value_Binary($stream),
                'sync_mod' => 2,
            ],
            ['sync_key = ?', ['{test}1']]
        );

        $row = $this->conn->selectOne(
            'SELECT sync_data, sync_pending FROM activesync_state WHERE sync_key = ?',
            ['{test}1']
        );

        $this->assertSame($folder, $row['sync_data']);
        $this->assertSame($pending, $row['sync_pending']);
    }
}
