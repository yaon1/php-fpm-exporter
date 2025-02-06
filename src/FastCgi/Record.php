<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\FastCgi;

/** FastCGI record framing, see https://fastcgi-archives.github.io/FastCGI_Specification.html */
final class Record
{
    public const VERSION = 1;
    public const BEGIN_REQUEST = 1;
    public const END_REQUEST = 3;
    public const PARAMS = 4;
    public const STDIN = 5;
    public const STDOUT = 6;
    public const STDERR = 7;

    public const RESPONDER = 1;
    public const HEADER_LENGTH = 8;
    public const MAX_CONTENT = 0xFFFF;

    public static function encode(int $type, int $requestId, string $content): string
    {
        if (strlen($content) > self::MAX_CONTENT) {
            throw new \LengthException('FastCGI record content is limited to 65535 bytes');
        }
        $length = strlen($content);
        $padding = (8 - $length % 8) % 8;

        return pack('CCnnCx', self::VERSION, $type, $requestId, $length, $padding) . $content . str_repeat("\0", $padding);
    }

    /** Splits arbitrary content into as many records as needed, terminated by an empty record. */
    public static function stream(int $type, int $requestId, string $content): string
    {
        $out = '';
        foreach ($content === '' ? [] : str_split($content, self::MAX_CONTENT) as $chunk) {
            $out .= self::encode($type, $requestId, $chunk);
        }

        return $out . self::encode($type, $requestId, '');
    }

    /**
     * @return array{type: int, requestId: int, contentLength: int, paddingLength: int}
     */
    public static function decodeHeader(string $header): array
    {
        if (strlen($header) !== self::HEADER_LENGTH) {
            throw new \UnexpectedValueException('FastCGI header must be 8 bytes');
        }
        $h = unpack('Cversion/Ctype/nrequestId/ncontentLength/CpaddingLength', $header);
        $int = static fn (string $key): int => is_array($h) && is_int($h[$key] ?? null) ? $h[$key] : -1;
        if ($int('version') !== self::VERSION) {
            throw new \UnexpectedValueException('Unsupported FastCGI version');
        }
        [$type, $requestId, $contentLength, $paddingLength] = [$int('type'), $int('requestId'), $int('contentLength'), $int('paddingLength')];

        return ['type' => $type, 'requestId' => $requestId, 'contentLength' => $contentLength, 'paddingLength' => $paddingLength];
    }
}
