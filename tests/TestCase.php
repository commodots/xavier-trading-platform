<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
