<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\Tests\FastCgi;

use PHPUnit\Framework\TestCase;
use Yaon\FpmExporter\FastCgi\Params;

final class ParamsTest extends TestCase
{
    public function testShortAndLongLengths(): void
    {
        self::assertSame("\x03\x02abcde", Params::encode(['abc' => 'de']));

        $long = str_repeat('v', 200);
        self::assertSame("\x01\x80\x00\x00\xC8k$long", Params::encode(['k' => $long]));
    }
}
