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

namespace Horde\Db\Test\Unit\Adapter\Pdo;

use Horde\Db\Adapter\Pdo\NullSerialInsert;
use PHPUnit\Framework\TestCase;

/**
 * @author     Torben Dannhauer <torben@dannhauer.de>
 * @category   Horde
 * @copyright  2026 The Horde Project
 * @license    http://www.horde.org/licenses/bsd BSD
 * @package    Db
 */
class NullSerialInsertTest extends TestCase
{
    public function testNullPlaceholderBecomesDefault(): void
    {
        [$sql, $parameters] = NullSerialInsert::apply(
            'INSERT INTO "kronolith_icalendar_storage" ("ical_id", "calendar_id", "event_uid") VALUES (?, ?, ?)',
            [null, 'cal', 'uid'],
            'ical_id'
        );

        $this->assertSame(
            'INSERT INTO "kronolith_icalendar_storage" ("ical_id", "calendar_id", "event_uid") VALUES (DEFAULT, ?, ?)',
            $sql
        );
        $this->assertSame(['cal', 'uid'], $parameters);
    }

    public function testLiteralNullBecomesDefault(): void
    {
        [$sql, $parameters] = NullSerialInsert::apply(
            'INSERT INTO storage (ical_id, event_uid) VALUES (NULL, ?)',
            ['uid'],
            'ical_id'
        );

        $this->assertSame(
            'INSERT INTO storage (ical_id, event_uid) VALUES (DEFAULT, ?)',
            $sql
        );
        $this->assertSame(['uid'], $parameters);
    }

    public function testExplicitPrimaryKeyIsKept(): void
    {
        $original = 'INSERT INTO "storage" ("ical_id", "event_uid") VALUES (?, ?)';
        [$sql, $parameters] = NullSerialInsert::apply($original, [4, 'uid'], 'ical_id');

        $this->assertSame($original, $sql);
        $this->assertSame([4, 'uid'], $parameters);
    }

    public function testNullInAnotherColumnIsKept(): void
    {
        $original = 'INSERT INTO "storage" ("ical_id", "event_uid") VALUES (?, ?)';
        [$sql, $parameters] = NullSerialInsert::apply($original, [4, null], 'ical_id');

        $this->assertSame($original, $sql);
        $this->assertSame([4, null], $parameters);
    }

    public function testInsertSelectIsLeftAlone(): void
    {
        $original = 'INSERT INTO storage (ical_id) SELECT NULL';
        [$sql, $parameters] = NullSerialInsert::apply($original, [], 'ical_id');

        $this->assertSame($original, $sql);
        $this->assertSame([], $parameters);
    }
}
