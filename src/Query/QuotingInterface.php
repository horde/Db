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

namespace Horde\Db\Query;

/**
 * Narrow interface for SQL quoting and dialect-specific operations.
 *
 * The query builder depends on this interface rather than the full Adapter.
 * This keeps the builder testable with a simple stub and decouples it
 * from the adapter inheritance hierarchy.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
interface QuotingInterface
{
    /**
     * Quote a column name for use in SQL.
     */
    public function quoteColumnName(string $name): string;

    /**
     * Quote a table name for use in SQL.
     */
    public function quoteTableName(string $name): string;

    /**
     * Return the SQL representation of boolean true.
     */
    public function quoteTrue(): string;

    /**
     * Return the SQL representation of boolean false.
     */
    public function quoteFalse(): string;

    /**
     * Append LIMIT and OFFSET to a SQL statement.
     *
     * Signature matches Horde_Db_Adapter for interface compatibility.
     *
     * @param string $sql     SQL statement.
     * @param array  $options Hash with 'limit' and (optional) 'offset' values.
     *
     * @return string Modified SQL.
     */
    public function addLimitOffset($sql, $options);

    /**
     * Append a locking clause to a SQL statement.
     *
     * Signature matches Horde_Db_Adapter for interface compatibility.
     *
     * @param string &$sql    SQL statement (modified in-place).
     * @param array  $options Lock options.
     */
    public function addLock(&$sql, array $options = []);
}
