<?php

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author     Torben Dannhauer <torben@dannhauer.de>
 * @category   Horde
 * @copyright  2026 The Horde Project
 * @license    http://www.horde.org/licenses/bsd BSD
 * @package    Db
 */

declare(strict_types=1);

namespace Horde\Db\Adapter\Pdo;

/**
 * Rewrites a null sequence primary key to DEFAULT in an INSERT.
 *
 * PostgreSQL applies nextval() only when a serial column is omitted or set
 * to DEFAULT. An explicit NULL violates NOT NULL. MySQL accepts that NULL
 * on AUTO_INCREMENT, so callers keep passing null and only this rewrite
 * changes the statement.
 *
 * @author     Torben Dannhauer <torben@dannhauer.de>
 * @category   Horde
 * @copyright  2026 The Horde Project
 * @license    http://www.horde.org/licenses/bsd BSD
 * @package    Db
 */
class NullSerialInsert
{
    /**
     * Replace a null primary-key value with DEFAULT.
     *
     * Statements that are not a single-row INSERT ... VALUES are returned
     * unchanged. A non-null primary key is left as written.
     *
     * @param string $sql         INSERT statement.
     * @param array  $parameters  Positional bind parameters for `?` placeholders.
     * @param string $primaryKey  Primary key column name.
     *
     * @return array{0: string, 1: array}  Rewritten SQL and bind parameters.
     */
    public static function apply(string $sql, array $parameters, string $primaryKey): array
    {
        if ($primaryKey === '' || !array_is_list($parameters)) {
            return [$sql, $parameters];
        }

        if (!preg_match(
            '/INSERT\s+INTO\s+\S+\s*\((.*)\)\s*VALUES\s*\((.*)\)\s*$/is',
            $sql,
            $match,
            PREG_OFFSET_CAPTURE
        )) {
            return [$sql, $parameters];
        }

        $columns = self::splitList($match[1][0]);
        $values = self::splitList($match[2][0]);
        if ($columns === null || $values === null || count($columns) !== count($values)) {
            return [$sql, $parameters];
        }

        $index = self::primaryKeyIndex($columns, $primaryKey);
        if ($index === null) {
            return [$sql, $parameters];
        }

        $token = $values[$index];
        if (strcasecmp($token, 'NULL') === 0) {
            $values[$index] = 'DEFAULT';
        } elseif ($token === '?') {
            $bindIndex = self::placeholderIndex($values, $index);
            if ($bindIndex === null || !array_key_exists($bindIndex, $parameters) || $parameters[$bindIndex] !== null) {
                return [$sql, $parameters];
            }
            $values[$index] = 'DEFAULT';
            array_splice($parameters, $bindIndex, 1);
        } else {
            return [$sql, $parameters];
        }

        $rewritten = substr($sql, 0, $match[2][1])
            . implode(', ', $values)
            . substr($sql, $match[2][1] + strlen($match[2][0]));

        return [$rewritten, $parameters];
    }

    /**
     * @param array<int, string> $columns
     */
    private static function primaryKeyIndex(array $columns, string $primaryKey): ?int
    {
        foreach ($columns as $index => $column) {
            $quoted = str_starts_with($column, '"');
            $name = self::unquote($column);
            if ($quoted ? $name === $primaryKey : strcasecmp($name, $primaryKey) === 0) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $values
     */
    private static function placeholderIndex(array $values, int $valueIndex): ?int
    {
        $bindIndex = -1;
        foreach ($values as $index => $value) {
            if ($value !== '?') {
                continue;
            }
            $bindIndex++;
            if ($index === $valueIndex) {
                return $bindIndex;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>|null
     */
    private static function splitList(string $list): ?array
    {
        $items = [];
        $current = '';
        $length = strlen($list);
        $quote = null;

        for ($i = 0; $i < $length; $i++) {
            $character = $list[$i];
            if ($quote !== null) {
                $current .= $character;
                if ($character === $quote) {
                    $escaped = ($i + 1) < $length && $list[$i + 1] === $quote;
                    if ($escaped) {
                        $current .= $list[++$i];
                        continue;
                    }
                    $quote = null;
                }
                continue;
            }
            if ($character === '"' || $character === "'") {
                $quote = $character;
                $current .= $character;
                continue;
            }
            if ($character === ',') {
                $items[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $character;
        }

        if ($quote !== null) {
            return null;
        }

        $items[] = trim($current);

        return $items;
    }

    private static function unquote(string $identifier): string
    {
        $identifier = trim($identifier);
        if (strlen($identifier) >= 2 && $identifier[0] === '"' && str_ends_with($identifier, '"')) {
            return str_replace('""', '"', substr($identifier, 1, -1));
        }

        return $identifier;
    }
}
