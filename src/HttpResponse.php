<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

final readonly class HttpResponse
{
    /** @param array<string, string> $headers */
    public function __construct(public int $status, public string $body, public array $headers = []) {}

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }
        echo $this->body;
    }
}
