<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\Tests\FastCgi;

use PHPUnit\Framework\TestCase;
use Yaon\FpmExporter\FastCgi\Response;

final class ResponseTest extends TestCase
{
    public function testParsesStatusHeadersAndBody(): void
    {
        $r = Response::fromCgiOutput("Status: 404 Not Found\r\nContent-Type: text/plain\r\n\r\nFile not found.\n");

        self::assertSame([404, 'text/plain', "File not found.\n"], [$r->status, $r->headers['content-type'], $r->body]);
    }

    public function testDefaultsToOk(): void
    {
        $r = Response::fromCgiOutput("Content-type: application/json\r\n\r\n{}");

        self::assertSame([200, '{}'], [$r->status, $r->body]);
    }
}
