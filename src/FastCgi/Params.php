<?php

declare(strict_types=1);

namespace Yaon\FpmExporter\FastCgi;

/** Name-value pair encoding used in FCGI_PARAMS records. */
final class Params
{
    /** @param array<string, string> $params */
    public static function encode(array $params): string
    {
        $out = '';
        foreach ($params as $name => $value) {
            $out .= self::length(strlen($name)) . self::length(strlen($value)) . $name . $value;
        }

        return $out;
    }

    /** Lengths below 128 take one byte; longer ones take four with the high bit set. */
    private static function length(int $length): string
    {
        return $length < 128 ? pack('C', $length) : pack('N', $length | 0x80000000);
    }
}
