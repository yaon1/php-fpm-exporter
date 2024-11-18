<?php

declare(strict_types=1);

namespace Yaon\FpmExporter;

/** @internal */
final class Json
{
    /** @return array<mixed> */
    public static function object(string $raw): array
    {
        $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \RuntimeException('Expected JSON object');
        }

        return $data;
    }

    /** @param array<mixed> $data */
    public static function number(array $data, string $key): int|float
    {
        $v = $data[$key] ?? 0;

        return is_int($v) || is_float($v) ? $v : (is_bool($v) ? (int) $v : 0);
    }

    /** @param array<mixed> $data
     *  @return array<mixed> */
    public static function nested(array $data, string $key): array
    {
        return is_array($data[$key] ?? null) ? $data[$key] : [];
    }
}
