<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\FastCgi;

/** Minimal blocking FastCGI client: one request per connection, enough for status pages. */
final readonly class Client
{
    private const REQUEST_ID = 1;

    /** @param string $address "tcp://host:port" or "unix:///path/to.sock" */
    public function __construct(private string $address, private float $timeout = 2.0) {}

    /** @param array<string, string> $params CGI environment, e.g. SCRIPT_FILENAME and QUERY_STRING */
    public function request(array $params, string $stdin = ''): Response
    {
        $socket = @stream_socket_client($this->address, $errno, $error, $this->timeout);
        if ($socket === false) {
            throw new \RuntimeException(sprintf('Cannot connect to %s: %s', $this->address, $error ?: "error $errno"));
        }
        stream_set_timeout($socket, (int) $this->timeout, (int) (fmod($this->timeout, 1) * 1_000_000));

        try {
            $this->write($socket, Record::encode(Record::BEGIN_REQUEST, self::REQUEST_ID, pack('nCx5', Record::RESPONDER, 0))
                . Record::stream(Record::PARAMS, self::REQUEST_ID, Params::encode($params))
                . Record::stream(Record::STDIN, self::REQUEST_ID, $stdin));

            return $this->read($socket);
        } finally {
            fclose($socket);
        }
    }

    /** @param resource $socket */
    private function write($socket, string $data): void
    {
        while ($data !== '') {
            $written = @fwrite($socket, $data);
            if ($written === false || $written === 0) {
                throw new \RuntimeException("Write to {$this->address} failed");
            }
            $data = substr($data, $written);
        }
    }

    /** @param resource $socket */
    private function read($socket): Response
    {
        $stdout = $stderr = '';
        while (true) {
            $header = Record::decodeHeader($this->readExactly($socket, Record::HEADER_LENGTH));
            $content = $this->readExactly($socket, $header['contentLength']);
            $this->readExactly($socket, $header['paddingLength']);

            match ($header['type']) {
                Record::STDOUT => $stdout .= $content,
                Record::STDERR => $stderr .= $content,
                Record::END_REQUEST => null,
                default => throw new \UnexpectedValueException("Unexpected FastCGI record type {$header['type']}"),
            };
            if ($header['type'] === Record::END_REQUEST) {
                return Response::fromCgiOutput($stdout, $stderr);
            }
        }
    }

    /** @param resource $socket */
    private function readExactly($socket, int $length): string
    {
        $buffer = '';
        while (strlen($buffer) < $length) {
            $chunk = fread($socket, max(1, $length - strlen($buffer)));
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($socket);
                throw new \RuntimeException($meta['timed_out'] ? "Timed out reading from {$this->address}" : "Connection to {$this->address} closed early");
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }
}
