<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\Tests\FastCgi;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Yaon\FpmExporter\FastCgi\Client;

/** Talks to a real php-fpm started for the test; skipped when the binary isn't installed. */
#[Group('integration')]
final class ClientTest extends TestCase
{
    /** @var resource|null */
    private static $process = null;
    private static string $dir = '';

    public static function setUpBeforeClass(): void
    {
        $binary = self::findFpm();
        if ($binary === null) {
            return;
        }
        self::$dir = sys_get_temp_dir() . '/fpm-exporter-' . getmypid();
        @mkdir(self::$dir);
        file_put_contents(self::$dir . '/hello.php', '<?php header("X-Test: 1"); echo "hi ", $_GET["name"] ?? "";');
        file_put_contents(self::$dir . '/fpm.conf', sprintf(
            "[global]\nerror_log = %s/fpm.log\ndaemonize = no\n[www]\nlisten = %s/fpm.sock\npm = static\npm.max_children = 2\npm.status_path = /status\nping.path = /ping\n",
            self::$dir,
            self::$dir,
        ));
        $process = proc_open([$binary, '-y', self::$dir . '/fpm.conf', '-n'], [], $pipes);
        self::$process = $process === false ? null : $process;
        for ($i = 0; $i < 50 && !file_exists(self::$dir . '/fpm.sock'); $i++) {
            usleep(100_000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$process !== null) {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }
    }

    private static function findFpm(): ?string
    {
        foreach ([getenv('PHP_FPM_BINARY') ?: '', '/opt/homebrew/opt/php/sbin/php-fpm', '/usr/sbin/php-fpm', '/usr/local/sbin/php-fpm'] as $path) {
            if ($path !== '' && is_executable($path)) {
                return $path;
            }
        }

        return null;
    }

    private function client(): Client
    {
        if (self::$process === null || !file_exists(self::$dir . '/fpm.sock')) {
            self::markTestSkipped('php-fpm is not available');
        }

        return new Client('unix://' . self::$dir . '/fpm.sock');
    }

    public function testRunsScript(): void
    {
        $response = $this->client()->request([
            'SCRIPT_FILENAME' => self::$dir . '/hello.php',
            'REQUEST_METHOD' => 'GET',
            'QUERY_STRING' => 'name=fpm',
        ]);

        self::assertSame([200, 'hi fpm', '1'], [$response->status, $response->body, $response->headers['x-test'] ?? null]);
    }

    public function testReadsStatusPageAsJson(): void
    {
        $response = $this->client()->request(['SCRIPT_NAME' => '/status', 'SCRIPT_FILENAME' => '/status', 'REQUEST_METHOD' => 'GET', 'QUERY_STRING' => 'json']);
        $status = json_decode($response->body, true);

        self::assertIsArray($status);
        self::assertSame('www', $status['pool']);
    }

    public function testConnectionErrorIsReported(): void
    {
        $this->expectExceptionMessageMatches('/Cannot connect/');
        (new Client('unix:///nonexistent.sock', 0.2))->request([]);
    }
}
