<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\Tests\FastCgi;

use PHPUnit\Framework\TestCase;
use Yaon\FpmExporter\FastCgi\Record;

final class RecordTest extends TestCase
{
    public function testEncodesWithPaddingToEightBytes(): void
    {
        $record = Record::encode(Record::STDIN, 1, 'abc');

        self::assertSame("\x01\x05\x00\x01\x00\x03\x05\x00abc\0\0\0\0\0", $record);
        self::assertSame(0, strlen($record) % 8);
    }

    public function testHeaderRoundTrip(): void
    {
        $header = Record::decodeHeader(substr(Record::encode(Record::STDOUT, 7, str_repeat('x', 10)), 0, 8));

        self::assertSame(['type' => Record::STDOUT, 'requestId' => 7, 'contentLength' => 10, 'paddingLength' => 6], $header);
    }

    public function testStreamSplitsLargeContentAndTerminates(): void
    {
        $stream = Record::stream(Record::PARAMS, 1, str_repeat('a', 70000));

        // 65535 + 4465 bytes of content, plus headers, padding and the empty terminator.
        self::assertSame(8 + 65535 + 1 + 8 + 4465 + 7 + 8, strlen($stream));
    }

    public function testRejectsUnknownVersion(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        Record::decodeHeader("\x02\x06\x00\x01\x00\x00\x00\x00");
    }
}
