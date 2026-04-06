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

declare(strict_types=1);

namespace Horde\Db\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use SplFileObject;
use Horde\Db\StatementParser;

/**
 * Test for PSR-4 StatementParser (Horde\Db\StatementParser).
 *
 * This tests the modern PSR-4 implementation in src/ directory.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 */
#[CoversClass(StatementParser::class)]
class Psr4StatementParserTest extends TestCase
{
    public function testParserFindsMultilineCreateStatement(): void
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

    public function testParserIteratesOverStatements(): void
    {
        $file = new SplFileObject(__DIR__ . '/fixtures/drop_create_table.sql', 'r');
        $parser = new StatementParser($file);

        $statements = [];
        foreach ($parser as $statement) {
            $statements[] = $statement;
        }

        $this->assertCount(5, $statements);
        $this->assertStringContainsString('DROP TABLE', $statements[0]);
        $this->assertStringContainsString('CREATE TABLE', $statements[3]);
    }

    public function testParserHandlesStringInput(): void
    {
        $parser = new StatementParser(__DIR__ . '/fixtures/drop_create_table.sql');

        $count = 0;
        foreach ($parser as $statement) {
            $count++;
        }

        $this->assertEquals(5, $count);
    }

    protected function assertParser(array $expectedStatements, string $filename): void
    {
        $file = new SplFileObject(__DIR__ . '/fixtures/' . $filename, 'r');
        $parser = new StatementParser($file);

        foreach ($expectedStatements as $i => $expected) {
            // Strip any whitespace before comparing the strings.
            $this->assertEquals(
                preg_replace('/\s/', '', $expected),
                preg_replace('/\s/', '', $parser->current()),
                "Parser differs on statement #$i"
            );
            $parser->next();
        }
    }
}
