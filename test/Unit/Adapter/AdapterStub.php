<?php

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

declare(strict_types=1);

namespace Horde\Db\Test\Unit\Adapter;

use Horde\Db\Adapter\Base;
use Horde\Db\Adapter\Base\Result;
use Horde_Support_Stub;
use RuntimeException;

/**
 * Minimal concrete subclass of Adapter\Base for unit testing.
 *
 * Provides no-op implementations of the methods that Base leaves to
 * concrete adapters (connect, select, execute, etc.). Only the quoting
 * proxy and schema delegation path is functional.
 */
class AdapterStub extends Base
{
    public function __construct()
    {
        // Skip parent constructor's connect() call — provide minimal init
        $this->cache = new Horde_Support_Stub();
        $this->logger = new Horde_Support_Stub();
        $this->config = ['charset' => 'UTF-8'];
        $this->runtime = 0;

        // Use the SQLite Schema which is concrete and provides ANSI-like quoting
        $this->schemaClass = \Horde\Db\Adapter\Sqlite\Schema::class;
    }

    public function connect(): void {}

    public function quoteString($string)
    {
        return "'" . str_replace("'", "''", $string) . "'";
    }

    public function select($sql, $arg1 = null, $arg2 = null): Result
    {
        throw new RuntimeException('Not implemented in stub');
    }

    public function selectAll($sql, $arg1 = null, $arg2 = null): array
    {
        return [];
    }

    public function selectOne($sql, $arg1 = null, $arg2 = null): array
    {
        return [];
    }

    public function selectValue($sql, $arg1 = null, $arg2 = null): string
    {
        return '';
    }

    public function selectValues($sql, $arg1 = null, $arg2 = null): array
    {
        return [];
    }

    public function selectAssoc($sql, $arg1 = null, $arg2 = null): array
    {
        return [];
    }

    public function execute($sql, $arg1 = null, $arg2 = null): mixed
    {
        return null;
    }

    public function insert($sql, $arg1 = null, $arg2 = null, $pk = null, $idValue = null, $sequenceName = null): int
    {
        return 0;
    }

    public function insertBlob($table, $fields, $pk = null, $idValue = null): int
    {
        return 0;
    }

    public function updateBlob($table, $fields, $where = ''): void {}

    public function beginDbTransaction(): void {}

    public function commitDbTransaction(): void {}

    public function rollbackDbTransaction(): void {}
}
