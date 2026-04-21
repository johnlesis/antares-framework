<?php

declare(strict_types=1);

namespace Antares\Http;

final class ResponseBag
{
    private static array $headers = [];

    public static function header(string $name, string $value): void
    {
        self::$headers[$name] = $value;
    }

    public static function getHeaders(): array
    {
        return self::$headers;
    }

    public static function clear(): void
    {
        self::$headers = [];
    }
}