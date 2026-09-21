<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Loads raw CSL provider payloads used as deterministic mock responses.
 *
 * Every fixture in tests/Fixtures/CSL is a raw CSL API response body
 * (ST/XT wrapper included) so the real service mapping is exercised.
 */
class CslFixture
{
    public static function path(string $name): string
    {
        return base_path('tests/Fixtures/CSL/'.$name.'.json');
    }

    public static function get(string $name): array
    {
        $path = self::path($name);

        if (! file_exists($path)) {
            throw new RuntimeException("CSL fixture not found: {$name}");
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new RuntimeException("Invalid CSL fixture: {$name}");
        }

        return $data;
    }
}
