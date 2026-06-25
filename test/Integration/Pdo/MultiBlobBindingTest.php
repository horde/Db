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
use Horde_Db_Value_Text;
use Horde\Db\Test\Integration\DatabaseTestCase;

/**
 * @covers Horde_Db_Adapter_Pdo_Base::_executePrepared
 * @covers Horde_Db_Adapter_Pdo_Base::_lobValueString
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

    /**
     * @dataProvider scalarBinaryPayloadProvider
     */
    public function testInsertBlobStoresScalarBinaryPayload($payload, $expected)
    {
        $key = '{test}scalar-insert-' . md5((string) $expected);

        $this->conn->insertBlob(
            'activesync_state',
            [
                'sync_key' => $key,
                'sync_data' => new Horde_Db_Value_Binary($payload),
                'sync_pending' => '',
                'sync_mod' => 0,
            ],
            'sync_key',
            $key
        );

        $stored = $this->conn->selectValue(
            'SELECT sync_data FROM activesync_state WHERE sync_key = ?',
            [$key]
        );

        $this->assertSame($expected, $stored);
    }

    /**
     * @dataProvider scalarBinaryPayloadProvider
     */
    public function testUpdateBlobRoundTripsScalarBinaryPayload($payload, $expected)
    {
        $this->conn->updateBlob(
            'activesync_state',
            [
                'sync_data' => new Horde_Db_Value_Binary($payload),
            ],
            ['sync_key = ?', ['{test}1']]
        );

        $stored = $this->conn->selectValue(
            'SELECT sync_data FROM activesync_state WHERE sync_key = ?',
            ['{test}1']
        );

        $this->assertSame($expected, $stored);
    }

    public static function scalarBinaryPayloadProvider(): array
    {
        return [
            'integer one' => [1, '1'],
            'integer zero' => [0, '0'],
            'string one' => ['1', '1'],
            'string zero' => ['0', '0'],
        ];
    }

    /**
     * Regression test for the PostgreSQL CLOB-in-bytea-binding bug.
     *
     * Before the fix, updateBlob() routed every Horde_Db_Value through the
     * PDO::PARAM_LOB binding path, which PDO PostgreSQL sends as a bytea
     * literal. Writing a Horde_Db_Value_Text into a text column therefore
     * stored the value as its "\x..." hex representation rather than the
     * original string. SQLite is too permissive to reproduce the corruption,
     * but it still exercises the code path that decides text values must NOT
     * use the LOB binding.
     */
    public function testUpdateBlobWithTextValueRoundTripsThroughTextColumn()
    {
        $pending = serialize([['id' => 1, 'type' => 'reminder', 'body' => 'hello']]);

        $this->conn->updateBlob(
            'activesync_state',
            [
                'sync_pending' => new Horde_Db_Value_Text($pending),
                'sync_mod' => 5,
            ],
            ['sync_key = ?', ['{test}1']]
        );

        $row = $this->conn->selectOne(
            'SELECT sync_pending, sync_mod FROM activesync_state WHERE sync_key = ?',
            ['{test}1']
        );

        $this->assertSame($pending, $row['sync_pending']);
        $this->assertStringStartsNotWith('\\x', (string) $row['sync_pending']);
        $this->assertSame(5, (int) $row['sync_mod']);
    }

    public function testUpdateBlobMixesTextAndBinaryValues()
    {
        $folder = str_repeat('H', 128);
        $pending = serialize([['id' => 7]]);

        $this->conn->updateBlob(
            'activesync_state',
            [
                'sync_data' => new Horde_Db_Value_Binary($folder),
                'sync_pending' => new Horde_Db_Value_Text($pending),
                'sync_mod' => 3,
            ],
            ['sync_key = ?', ['{test}1']]
        );

        $row = $this->conn->selectOne(
            'SELECT sync_data, sync_pending, sync_mod FROM activesync_state WHERE sync_key = ?',
            ['{test}1']
        );

        $this->assertSame($folder, $row['sync_data']);
        $this->assertSame($pending, $row['sync_pending']);
        $this->assertSame(3, (int) $row['sync_mod']);
    }
}
