<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\FastCgi;

final readonly class Response
{
    /** @param array<string, string> $headers lower-cased names */
    public function __construct(public int $status, public array $headers, public string $body, public string $stderr = '')
    {
    }

    /** Splits CGI output ("Header: v\r\n...\r\n\r\nbody") into status, headers and body. */
    public static function fromCgiOutput(string $stdout, string $stderr = ''): self
    {
        $separator = strpos($stdout, "\r\n\r\n");
        [$head, $body] = $separator === false ? ['', $stdout] : [substr($stdout, 0, $separator), substr($stdout, $separator + 4)];

        $headers = [];
        foreach (explode("\r\n", $head) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }
        $status = isset($headers['status']) ? (int) $headers['status'] : 200;

        return new self($status, $headers, $body, $stderr);
    }
}
