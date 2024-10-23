<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\Tests;

use PHPUnit\Framework\TestCase;
use Yaon\FpmExporter\Metric;
use Yaon\FpmExporter\TextFormatter;

final class TextFormatterTest extends TestCase
{
    public function testGroupsHelpAndTypeAndEscapesLabels(): void
    {
        $out = (new TextFormatter())->format([
            new Metric('phpfpm_up', 'gauge', 'Is FPM reachable', 1, ['pool' => 'www']),
            new Metric('phpfpm_up', 'gauge', 'Is FPM reachable', 0, ['pool' => 'a"b']),
            new Metric('opcache_hit_ratio', 'gauge', 'Hit ratio', 0.875),
        ]);

        self::assertSame(
            "# HELP phpfpm_up Is FPM reachable\n# TYPE phpfpm_up gauge\n"
            . "phpfpm_up{pool=\"www\"} 1\nphpfpm_up{pool=\"a\\\"b\"} 0\n"
            . "# HELP opcache_hit_ratio Hit ratio\n# TYPE opcache_hit_ratio gauge\nopcache_hit_ratio 0.875\n",
            $out,
        );
    }
}
