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

use Horde\Test\TestCase;
use Horde\Db\SearchParser;
use Horde\Db\DbException;

/**
 * Test for SearchParser.
 *
 * Tests the Google-like search expression parser that generates SQL WHERE clauses.
 * This is security-critical code that processes user input and generates SQL.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 * @covers   \Horde\Db\SearchParser
 */
class SearchParserTest extends TestCase
{
    /**
     * Test simple single keyword search.
     */
    public function testSimpleKeyword(): void
    {
        $result = SearchParser::parse('column_name', 'cat');
        $this->assertEquals("(LOWER(column_name) LIKE '%cat%')", $result);
    }

    /**
     * Test multiple keywords with implicit AND.
     */
    public function testMultipleKeywordsImplicitAnd(): void
    {
        $result = SearchParser::parse('column_name', 'cat dog');
        $this->assertEquals(
            "((LOWER(column_name) LIKE '%cat%') AND (LOWER(column_name) LIKE '%dog%'))",
            $result
        );
    }

    /**
     * Test explicit AND operator.
     */
    public function testAndOperator(): void
    {
        $result = SearchParser::parse('column_name', 'cat and dog');
        $this->assertEquals(
            "((LOWER(column_name) LIKE '%cat%') AND (LOWER(column_name) LIKE '%dog%'))",
            $result
        );
    }

    /**
     * Test explicit AND operator with mixed case.
     */
    public function testAndOperatorCaseInsensitive(): void
    {
        $result = SearchParser::parse('column_name', 'cat AND dog');
        $this->assertEquals(
            "((LOWER(column_name) LIKE '%cat%') AND (LOWER(column_name) LIKE '%dog%'))",
            $result
        );

        $result = SearchParser::parse('column_name', 'cat And dog');
        $this->assertEquals(
            "((LOWER(column_name) LIKE '%cat%') AND (LOWER(column_name) LIKE '%dog%'))",
            $result
        );
    }

    /**
     * Test OR operator.
     */
    public function testOrOperator(): void
    {
        $result = SearchParser::parse('column_name', 'cat or dog');
        $this->assertEquals(
            "((LOWER(column_name) LIKE '%cat%') OR (LOWER(column_name) LIKE '%dog%'))",
            $result
        );
    }

    /**
     * Test OR operator with mixed case.
     */
    public function testOrOperatorCaseInsensitive(): void
    {
        $result = SearchParser::parse('column_name', 'cat OR dog');
        $this->assertEquals(
            "((LOWER(column_name) LIKE '%cat%') OR (LOWER(column_name) LIKE '%dog%'))",
            $result
        );
    }

    /**
     * Test comma as OR operator.
     */
    public function testCommaAsOr(): void
    {
        $result = SearchParser::parse('column_name', 'cat, dog');
        $this->assertEquals(
            "((LOWER(column_name) LIKE '%cat%') OR (LOWER(column_name) LIKE '%dog%'))",
            $result
        );
    }

    /**
     * Test NOT operator.
     */
    public function testNotOperator(): void
    {
        $result = SearchParser::parse('column_name', 'cat not dog');
        $this->assertEquals(
            "((LOWER(column_name) LIKE '%cat%') AND (NOT (LOWER(column_name) LIKE '%dog%')))",
            $result
        );
    }

    /**
     * Test NOT operator with mixed case.
     */
    public function testNotOperatorCaseInsensitive(): void
    {
        $result = SearchParser::parse('column_name', 'cat NOT dog');
        $this->assertEquals(
            "((LOWER(column_name) LIKE '%cat%') AND (NOT (LOWER(column_name) LIKE '%dog%')))",
            $result
        );
    }

    /**
     * Test simple parentheses grouping.
     */
    public function testSimpleParentheses(): void
    {
        $result = SearchParser::parse('column_name', '(cat)');
        $this->assertEquals("(LOWER(column_name) LIKE '%cat%')", $result);
    }

    /**
     * Test complex grouping with parentheses.
     */
    public function testComplexGrouping(): void
    {
        $result = SearchParser::parse('column_name', 'cat and (dog or puppy)');
        $this->assertEquals(
            "((LOWER(column_name) LIKE '%cat%') AND ((LOWER(column_name) LIKE '%dog%') OR (LOWER(column_name) LIKE '%puppy%')))",
            $result
        );
    }

    /**
     * Test NOT with parentheses grouping.
     */
    public function testNotWithGrouping(): void
    {
        $result = SearchParser::parse('column_name', 'cat and not (dog or puppy)');
        $this->assertEquals(
            "((LOWER(column_name) LIKE '%cat%') AND (NOT ((LOWER(column_name) LIKE '%dog%') OR (LOWER(column_name) LIKE '%puppy%'))))",
            $result
        );
    }

    /**
     * Test nested parentheses.
     */
    public function testNestedParentheses(): void
    {
        $result = SearchParser::parse('column_name', '((cat))');
        $this->assertEquals("(LOWER(column_name) LIKE '%cat%')", $result);
    }

    /**
     * Test quoted string.
     */
    public function testQuotedString(): void
    {
        $result = SearchParser::parse('column_name', '"cat and dog"');
        $this->assertEquals("(LOWER(column_name) LIKE '%cat and dog%')", $result);
    }

    /**
     * Test quoted string with spaces.
     */
    public function testQuotedStringWithSpaces(): void
    {
        $result = SearchParser::parse('column_name', '"the quick brown fox"');
        $this->assertEquals("(LOWER(column_name) LIKE '%the quick brown fox%')", $result);
    }

    /**
     * Test that operators in quoted strings are treated as literals.
     */
    public function testQuotedOperatorAsLiteral(): void
    {
        $result = SearchParser::parse('column_name', '"or"');
        $this->assertEquals("(LOWER(column_name) LIKE '%or%')", $result);

        $result = SearchParser::parse('column_name', '"and"');
        $this->assertEquals("(LOWER(column_name) LIKE '%and%')", $result);

        $result = SearchParser::parse('column_name', '"not"');
        $this->assertEquals("(LOWER(column_name) LIKE '%not%')", $result);
    }

    /**
     * Test quoted string with parentheses as literal.
     */
    public function testQuotedParenthesesAsLiteral(): void
    {
        $result = SearchParser::parse('column_name', '"(test)"');
        $this->assertEquals("(LOWER(column_name) LIKE '%(test)%')", $result);
    }

    /**
     * Test combining quoted and unquoted terms.
     */
    public function testMixedQuotedAndUnquoted(): void
    {
        $result = SearchParser::parse('column_name', 'cat "and dog"');
        $this->assertEquals(
            "((LOWER(column_name) LIKE '%cat%') AND (LOWER(column_name) LIKE '%and dog%'))",
            $result
        );
    }

    /**
     * Test percent sign escaping to prevent LIKE wildcard injection.
     */
    public function testPercentSignEscaping(): void
    {
        $result = SearchParser::parse('column_name', 'test%');
        $this->assertStringContainsString('\\%', $result);
        // The actual output has double backslash escaping
        $this->assertEquals("(LOWER(column_name) LIKE '%test\\\\%%')", $result);
    }

    /**
     * Test multiple percent signs are escaped.
     */
    public function testMultiplePercentSignsEscaped(): void
    {
        $result = SearchParser::parse('column_name', '%%test%%');
        // The actual output has double backslash escaping
        $this->assertEquals("(LOWER(column_name) LIKE '%\\\\%\\\\%test\\\\%\\\\%%')", $result);
    }

    /**
     * Test single quote escaping for SQL injection prevention.
     */
    public function testSingleQuoteEscaping(): void
    {
        $result = SearchParser::parse('column_name', "test'value");
        $this->assertStringContainsString("\\'", $result);
        $this->assertEquals("(LOWER(column_name) LIKE '%test\\'value%')", $result);
    }

    /**
     * Test SQL injection attempt with quotes.
     */
    public function testSqlInjectionWithQuotes(): void
    {
        $result = SearchParser::parse('column_name', "' OR '1'='1");
        // Should escape the quotes, not allow SQL injection
        $this->assertStringContainsString("\\'", $result);
        $this->assertStringNotContainsString("' OR '1'='1'", $result);
    }

    /**
     * Test SQL injection attempt with comment.
     */
    public function testSqlInjectionWithComment(): void
    {
        $result = SearchParser::parse('column_name', "test' --");
        // Should escape the quote and include the dashes as part of search
        $this->assertStringContainsString("\\'", $result);
        $this->assertStringContainsString("--", $result);
    }

    /**
     * Test backslash escaping.
     */
    public function testBackslashEscaping(): void
    {
        $result = SearchParser::parse('column_name', 'test\\value');
        $this->assertStringContainsString('\\\\', $result);
    }

    /**
     * Test special characters in search.
     */
    public function testSpecialCharacters(): void
    {
        $result = SearchParser::parse('column_name', 'test@example.com');
        $this->assertEquals("(LOWER(column_name) LIKE '%test@example.com%')", $result);
    }

    /**
     * Test Unicode characters.
     */
    public function testUnicodeCharacters(): void
    {
        $result = SearchParser::parse('column_name', 'café');
        $this->assertStringContainsString('café', $result);
    }

    /**
     * Test empty expression throws exception.
     */
    public function testEmptyExpressionThrowsException(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('Empty search terms');
        SearchParser::parse('column_name', '');
    }

    /**
     * Test whitespace-only expression throws exception.
     */
    public function testWhitespaceOnlyThrowsException(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('Empty search terms');
        SearchParser::parse('column_name', '   ');
    }

    /**
     * Test unbalanced opening parenthesis throws exception.
     */
    public function testUnbalancedOpeningParenthesisThrowsException(): void
    {
        $this->expectException(DbException::class);
        $this->expectExceptionMessage('Expected ")"');
        SearchParser::parse('column_name', '(cat');
    }

    /**
     * Test mismatched parentheses (unclosed opening).
     */
    public function testMismatchedParentheses(): void
    {
        $this->expectException(DbException::class);
        SearchParser::parse('column_name', '((cat)');
    }

    /**
     * Test empty parentheses throw exception.
     */
    public function testEmptyParenthesesThrowException(): void
    {
        $this->expectException(DbException::class);
        SearchParser::parse('column_name', '()');
    }

    /**
     * Test only NOT operator throws exception.
     */
    public function testOnlyNotOperatorThrowsException(): void
    {
        $this->expectException(DbException::class);
        SearchParser::parse('column_name', 'not');
    }

    /**
     * Test only OR operator throws exception.
     */
    public function testOnlyOrOperatorThrowsException(): void
    {
        $this->expectException(DbException::class);
        SearchParser::parse('column_name', 'or');
    }

    /**
     * Test complex real-world search expression.
     */
    public function testComplexRealWorldExpression(): void
    {
        $result = SearchParser::parse(
            'description',
            'urgent and (bug or error) not "working as intended"'
        );

        // Should generate valid SQL with proper grouping
        $this->assertStringContainsString('urgent', $result);
        $this->assertStringContainsString('bug', $result);
        $this->assertStringContainsString('error', $result);
        $this->assertStringContainsString('working as intended', $result);
        $this->assertStringContainsString('AND', $result);
        $this->assertStringContainsString('OR', $result);
        $this->assertStringContainsString('NOT', $result);
    }

    /**
     * Test that column name is used correctly in output.
     */
    public function testColumnNameInOutput(): void
    {
        $result = SearchParser::parse('my_column', 'test');
        $this->assertStringContainsString('my_column', $result);

        $result = SearchParser::parse('different_column', 'test');
        $this->assertStringContainsString('different_column', $result);
    }

    /**
     * Test lowercase conversion of search terms.
     */
    public function testLowercaseConversion(): void
    {
        $result = SearchParser::parse('column_name', 'TEST');
        $this->assertStringContainsString('%test%', $result);

        $result = SearchParser::parse('column_name', 'TeSt');
        $this->assertStringContainsString('%test%', $result);
    }

    /**
     * Test case-insensitive matching via LOWER().
     */
    public function testCaseInsensitiveMatching(): void
    {
        $result = SearchParser::parse('column_name', 'CaT');
        $this->assertStringStartsWith('(LOWER(', $result);
        $this->assertStringContainsString('LIKE', $result);
    }
}
