<?php

declare(strict_types=1);

/**
 * Regression test for the PostgreSQL CLOB-in-bytea-binding bug in updateBlob().
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

namespace Horde\Db\Test\Integration\Postgres;

use Horde_Db_Adapter_Pdo_Postgresql;
use Horde_Db_Value_Binary;
use Horde_Db_Value_Text;
use Horde\Db\Test\Integration\DatabaseTestCase;

/**
 * Verifies that updateBlob() writes Horde_Db_Value_Text into a text column as
 * a normal string literal, not via the PDO::PARAM_LOB / bytea binding path.
 *
 * Before the fix, the value was stored as its "\x..." hex representation,
 * corrupting CLOB columns on every update (e.g. Horde_Alarm reminders).
 *
 * @covers Horde_Db_Adapter_Pdo_Base::updateBlob
 */
class UpdateBlobTextRegressionTest extends DatabaseTestCase
{
    private $conn;

    protected function setUp(): void
    {
        $this->requireDatabase('postgres');

        $config = $this->getPostgresConfig();
        $this->conn = new Horde_Db_Adapter_Pdo_Postgresql($config);

        $this->conn->execute('DROP TABLE IF EXISTS test_blob_text');
        $this->conn->execute(
            'CREATE TABLE test_blob_text (
                key TEXT PRIMARY KEY,
                clob_col TEXT,
                blob_col BYTEA
            )'
        );
        $this->conn->insertBlob(
            'test_blob_text',
            [
                'key' => 'row1',
                'clob_col' => 'initial-clob',
                'blob_col' => new Horde_Db_Value_Binary('initial-blob'),
            ],
            'key',
            'row1'
        );
    }

    protected function tearDown(): void
    {
        if ($this->conn) {
            $this->dropTables($this->conn, ['test_blob_text']);
            $this->conn->disconnect();
        }
    }

    public function testUpdateBlobStoresTextValueAsPlainString()
    {
        $payload = serialize([
            'subject' => 'Reminder',
            'body' => 'Don\'t forget to feed the cat.',
        ]);

        $this->conn->updateBlob(
            'test_blob_text',
            ['clob_col' => new Horde_Db_Value_Text($payload)],
            ['key = ?', ['row1']]
        );

        $stored = $this->conn->selectValue(
            'SELECT clob_col FROM test_blob_text WHERE key = ?',
            ['row1']
        );

        $this->assertSame($payload, $stored);
        $this->assertStringStartsNotWith('\\x', (string) $stored);
    }

    public function testUpdateBlobStillRoundTripsBinaryValues()
    {
        $payload = random_bytes(64);

        $this->conn->updateBlob(
            'test_blob_text',
            ['blob_col' => new Horde_Db_Value_Binary($payload)],
            ['key = ?', ['row1']]
        );

        $stored = $this->conn->selectValue(
            'SELECT blob_col FROM test_blob_text WHERE key = ?',
            ['row1']
        );

        $this->assertSame($payload, $stored);
    }

    public function testUpdateBlobMixesTextAndBinaryAcrossColumns()
    {
        $text = serialize(['who' => 'Alice', 'note' => 'mixed-update']);
        $binary = random_bytes(32);

        $this->conn->updateBlob(
            'test_blob_text',
            [
                'clob_col' => new Horde_Db_Value_Text($text),
                'blob_col' => new Horde_Db_Value_Binary($binary),
            ],
            ['key = ?', ['row1']]
        );

        $row = $this->conn->selectOne(
            'SELECT clob_col, blob_col FROM test_blob_text WHERE key = ?',
            ['row1']
        );

        $this->assertSame($text, $row['clob_col']);
        $this->assertStringStartsNotWith('\\x', (string) $row['clob_col']);
        $this->assertSame($binary, $row['blob_col']);
    }
}
