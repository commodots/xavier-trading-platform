<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Loads raw Alpaca provider payloads used as deterministic mock responses.
 *
 * Every fixture in tests/Fixtures/Alpaca is a raw Alpaca order object exactly
 * as the REST API returns it (string quantities included) so the real
 * normalisation code is exercised rather than a hand-built array.
 */
class AlpacaFixture
{
    public static function path(string $name): string
    {
        return base_path('tests/Fixtures/Alpaca/'.$name.'.json');
    }

    public static function get(string $name): array
    {
        $path = self::path($name);

        if (! file_exists($path)) {
            throw new RuntimeException("Alpaca fixture not found: {$name}");
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new RuntimeException("Invalid Alpaca fixture: {$name}");
        }

        return $data;
    }
}
