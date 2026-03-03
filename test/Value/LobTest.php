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

namespace Horde\Db\Test\Value;

use Horde\Test\TestCase;
use Horde\Db\Value\Binary;
use Horde\Db\Value\Text;
use Horde\Db\Adapter;

/**
 * Test for Value\Lob (tested via Binary and Text subclasses).
 *
 * Tests LOB (Large Object) value handling including string and stream storage.
 * The Lob abstract class provides stream handling for binary and text data.
 *
 * @category Horde
 * @package  Db
 * @license  http://www.horde.org/licenses/bsd
 * @covers   \Horde\Db\Value\Lob
 * @covers   \Horde\Db\Value\Binary
 * @covers   \Horde\Db\Value\Text
 */
class LobTest extends TestCase
{
    /**
     * Create a test stream resource.
     */
    private function createStream(string $content)
    {
        $stream = fopen('php://temp', 'r+');
        if ($content !== '') {
            fwrite($stream, $content);
            rewind($stream);
        }
        return $stream;
    }

    /**
     * Read stream contents.
     */
    private function readStream($stream): string
    {
        rewind($stream);
        return stream_get_contents($stream);
    }

    /**
     * Test Binary construction with string.
     */
    public function testBinaryConstructWithString(): void
    {
        $binary = new Binary('test data');
        $this->assertInstanceOf(Binary::class, $binary);
    }

    /**
     * Test Binary construction with stream.
     */
    public function testBinaryConstructWithStream(): void
    {
        $stream = $this->createStream('test data');
        $binary = new Binary($stream);
        $this->assertInstanceOf(Binary::class, $binary);
        fclose($stream);
    }

    /**
     * Test Text construction with string.
     */
    public function testTextConstructWithString(): void
    {
        $text = new Text('test text');
        $this->assertInstanceOf(Text::class, $text);
    }

    /**
     * Test Text construction with stream.
     */
    public function testTextConstructWithStream(): void
    {
        $stream = $this->createStream('test text');
        $text = new Text($stream);
        $this->assertInstanceOf(Text::class, $text);
        fclose($stream);
    }

    /**
     * Test value getter returns string when constructed with string.
     */
    public function testGetValueFromString(): void
    {
        $binary = new Binary('test data');
        $this->assertEquals('test data', $binary->value);
    }

    /**
     * Test value getter returns stream contents when constructed with stream.
     */
    public function testGetValueFromStream(): void
    {
        $stream = $this->createStream('test data from stream');
        $binary = new Binary($stream);

        $value = $binary->value;
        $this->assertEquals('test data from stream', $value);

        fclose($stream);
    }

    /**
     * Test value getter with empty stream.
     */
    public function testGetValueFromEmptyStream(): void
    {
        $stream = $this->createStream('');
        $binary = new Binary($stream);

        $value = $binary->value;
        $this->assertEquals('', $value);

        fclose($stream);
    }

    /**
     * Test stream getter returns stream when constructed with stream.
     */
    public function testGetStreamFromStream(): void
    {
        $originalStream = $this->createStream('test data');
        $binary = new Binary($originalStream);

        $stream = $binary->stream;
        $this->assertIsResource($stream);
        $this->assertEquals('test data', $this->readStream($stream));

        fclose($originalStream);
    }

    /**
     * Test stream getter creates stream when constructed with string.
     */
    public function testGetStreamFromString(): void
    {
        $binary = new Binary('test data from string');

        $stream = $binary->stream;
        $this->assertIsResource($stream);
        $this->assertEquals('test data from string', $this->readStream($stream));

        fclose($stream);
    }

    /**
     * Test stream getter with empty string.
     */
    public function testGetStreamFromEmptyString(): void
    {
        $binary = new Binary('');

        $stream = $binary->stream;
        $this->assertIsResource($stream);
        $this->assertEquals('', $this->readStream($stream));

        fclose($stream);
    }

    /**
     * Test stream position is reset before reading.
     */
    public function testStreamRewindBeforeRead(): void
    {
        $stream = $this->createStream('test data');

        // Move position forward
        fseek($stream, 5);

        $binary = new Binary($stream);

        // Getting value should rewind and read from start
        $value = $binary->value;
        $this->assertEquals('test data', $value);

        fclose($stream);
    }

    /**
     * Test multiple value reads return same data.
     */
    public function testMultipleValueReads(): void
    {
        $stream = $this->createStream('test data');
        $binary = new Binary($stream);

        $value1 = $binary->value;
        $value2 = $binary->value;

        $this->assertEquals('test data', $value1);
        $this->assertEquals('test data', $value2);
        $this->assertEquals($value1, $value2);

        fclose($stream);
    }

    /**
     * Test value setter.
     */
    public function testSetValue(): void
    {
        $binary = new Binary('original');
        $binary->value = 'updated';

        $this->assertEquals('updated', $binary->value);
    }

    /**
     * Test stream setter.
     */
    public function testSetStream(): void
    {
        $stream1 = $this->createStream('original');
        $binary = new Binary($stream1);

        $stream2 = $this->createStream('updated');
        $binary->stream = $stream2;

        $this->assertEquals('updated', $binary->value);

        fclose($stream1);
        fclose($stream2);
    }

    /**
     * Test Binary roundtrip with string value.
     */
    public function testBinaryValueRoundtrip(): void
    {
        $data = 'binary test data';
        $binary = new Binary($data);

        $this->assertEquals($data, $binary->value);
    }

    /**
     * Test Binary roundtrip with stream.
     */
    public function testBinaryStreamRoundtrip(): void
    {
        $data = 'stream binary data';
        $stream = $this->createStream($data);
        $binary = new Binary($stream);

        // Get as value
        $this->assertEquals($data, $binary->value);

        // Get as stream
        $resultStream = $binary->stream;
        $this->assertEquals($data, $this->readStream($resultStream));

        fclose($stream);
    }

    /**
     * Test Text roundtrip with string value.
     */
    public function testTextValueRoundtrip(): void
    {
        $data = 'text data with unicode: café';
        $text = new Text($data);

        $this->assertEquals($data, $text->value);
    }

    /**
     * Test Text roundtrip with stream.
     */
    public function testTextStreamRoundtrip(): void
    {
        $data = 'stream text data';
        $stream = $this->createStream($data);
        $text = new Text($stream);

        // Get as value
        $this->assertEquals($data, $text->value);

        // Get as stream
        $resultStream = $text->stream;
        $this->assertEquals($data, $this->readStream($resultStream));

        fclose($stream);
    }

    /**
     * Test large binary data (5000+ bytes).
     */
    public function testLargeBinaryData(): void
    {
        // Create 5KB of binary data
        $data = str_repeat('x', 5000);
        $binary = new Binary($data);

        $this->assertEquals(5000, strlen($binary->value));
        $this->assertEquals($data, $binary->value);
    }

    /**
     * Test large stream data.
     */
    public function testLargeStreamData(): void
    {
        // Create 10KB of data
        $data = str_repeat('y', 10000);
        $stream = $this->createStream($data);
        $binary = new Binary($stream);

        $value = $binary->value;
        $this->assertEquals(10000, strlen($value));
        $this->assertEquals($data, $value);

        fclose($stream);
    }

    /**
     * Test unicode text handling.
     */
    public function testUnicodeText(): void
    {
        $data = 'Unicode: 你好世界 café résumé';
        $text = new Text($data);

        $this->assertEquals($data, $text->value);
    }

    /**
     * Test empty value.
     */
    public function testEmptyValue(): void
    {
        $binary = new Binary('');
        $this->assertEquals('', $binary->value);

        $stream = $binary->stream;
        $this->assertEquals('', $this->readStream($stream));
        fclose($stream);
    }

    /**
     * Test null byte in binary data.
     */
    public function testNullByte(): void
    {
        $data = "test\x00data";
        $binary = new Binary($data);

        $this->assertEquals($data, $binary->value);
        $this->assertStringContainsString("\x00", $binary->value);
    }

    /**
     * Test null bytes preserved through stream.
     */
    public function testNullBytePreservedThroughStream(): void
    {
        $data = "before\x00after";
        $stream = $this->createStream($data);
        $binary = new Binary($stream);

        $value = $binary->value;
        $this->assertEquals($data, $value);
        $this->assertStringContainsString("\x00", $value);

        fclose($stream);
    }

    /**
     * Test Text quote method with mock adapter.
     * Binary quote method is tested via integration tests with real adapters.
     */
    public function testTextQuote(): void
    {
        $mockAdapter = $this->createMock(Adapter::class);
        $mockAdapter
            ->expects($this->once())
            ->method('quoteString')
            ->with('text data')
            ->willReturn("'quoted text'");

        $text = new Text('text data');
        $result = $text->quote($mockAdapter);

        $this->assertEquals("'quoted text'", $result);
    }

    /**
     * Test switching from value to stream and back.
     */
    public function testSwitchBetweenValueAndStream(): void
    {
        $binary = new Binary('initial value');

        // Get as value
        $value1 = $binary->value;
        $this->assertEquals('initial value', $value1);

        // Get as stream
        $stream = $binary->stream;
        $this->assertEquals('initial value', $this->readStream($stream));

        // Get as value again
        $value2 = $binary->value;
        $this->assertEquals('initial value', $value2);

        fclose($stream);
    }

    /**
     * Test Text with special SQL characters.
     */
    public function testTextWithSqlCharacters(): void
    {
        $data = "Text with 'quotes' and \"double quotes\" and \\ backslash";
        $text = new Text($data);

        $this->assertEquals($data, $text->value);
    }

    /**
     * Test that stream-to-value conversion uses php://temp.
     */
    public function testStreamCreationFromValue(): void
    {
        $binary = new Binary('test');

        $stream = $binary->stream;
        $this->assertIsResource($stream);

        // Verify it's readable/writable
        $meta = stream_get_meta_data($stream);
        $this->assertStringContainsString('TEMP', strtoupper($meta['uri']));

        fclose($stream);
    }
}
