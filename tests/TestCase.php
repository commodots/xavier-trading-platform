<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function cslFixture(string $name): array
    {
        $path = base_path('tests/Fixtures/CSL/'.$name.'.json');

        if (! file_exists($path)) {
            throw new \RuntimeException("CSL fixture not found: {$name}");
        }

        $payload = json_decode((string) file_get_contents($path), true);

        return is_array($payload) ? $payload : [];
    }

    public function actingAs($user, $driver = null)
    {
        if ($driver === null) {
            parent::actingAs($user);

            // Create a real personal access token and use it for API requests.
            // This also preserves default session-based auth for web tests.
            $token = $user->createToken('test-device')->plainTextToken;
            return $this->withHeader('Authorization', 'Bearer ' . $token);
        }

        return parent::actingAs($user, $driver);
    }
}
