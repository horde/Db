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

namespace Horde\Db\Test\Unit\Query;

use Horde\Db\Query\QuotingInterface;

/**
 * ANSI SQL quoting stub for testing query builders without a real adapter.
 */
class AnsiQuotingStub implements QuotingInterface
{
    public function quoteColumnName(string $name): string
    {
        if (str_contains($name, '.')) {
            $parts = explode('.', $name, 2);
            return '"' . $parts[0] . '"."' . $parts[1] . '"';
        }
        return '"' . $name . '"';
    }

    public function quoteTableName(string $name): string
    {
        return '"' . $name . '"';
    }

    public function quoteTrue(): string
    {
        return '1';
    }

    public function quoteFalse(): string
    {
        return '0';
    }

    public function addLimitOffset($sql, $options)
    {
        if (isset($options['limit']) && $options['limit'] !== null) {
            $sql .= ' LIMIT ' . (int) $options['limit'];
        }
        if (isset($options['offset']) && $options['offset'] !== null) {
            $sql .= ' OFFSET ' . (int) $options['offset'];
        }
        return $sql;
    }

    public function addLock(&$sql, array $options = [])
    {
        if (isset($options['lock']) && is_string($options['lock'])) {
            $sql .= ' ' . $options['lock'];
        } else {
            $sql .= ' FOR UPDATE';
        }
    }
}
