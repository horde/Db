<?php

/**
 * Copyright 2006-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */

namespace Horde\Db\Test;

use Horde\Test\TestCase;
use SplFileObject;
use Horde_Db_StatementParser;

/**
 * Test for PSR-0 StatementParser (Horde_Db_StatementParser).
 *
 * This tests the legacy PSR-0 implementation in lib/ directory.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 * @covers   Horde_Db_StatementParser
 */
class Psr0StatementParserTest extends TestCase
{
    public function testParserFindsMultilineCreateStatement()
    {
        $expected = [
            'DROP TABLE IF EXISTS `exp_actions`',
            'SET @saved_cs_client     = @@character_set_client',
            'SET character_set_client = utf8',
            'CREATE TABLE `exp_actions` (
              `action_id` int(4) unsigned NOT NULL auto_increment,
              `class` varchar(50) NOT NULL,
              `method` varchar(50) NOT NULL,
              PRIMARY KEY  (`action_id`)
            ) ENGINE=MyISAM AUTO_INCREMENT=20 DEFAULT CHARSET=latin1',
            'SET character_set_client = @saved_cs_client',
        ];
        $this->assertParser($expected, 'drop_create_table.sql');
    }

    public function assertParser(array $expectedStatements, $filename)
    {
        $file = new SplFileObject(__DIR__ . '/fixtures/' . $filename, 'r');
        $parser = new Horde_Db_StatementParser($file);

        foreach ($expectedStatements as $i => $expected) {
            // Strip any whitespace before comparing the strings.
            $this->assertEquals(
                preg_replace('/\s/', '', $expected),
                preg_replace('/\s/', '', $parser->next()),
                "Parser differs on statement #$i"
            );
        }
    }
}
