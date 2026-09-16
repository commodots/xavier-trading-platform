<?php

use App\Models\PlatformSetting;

if (! function_exists('setting')) {
    /**
     * Get a platform setting value
     *
     * @param  mixed  $default
     * @return mixed
     */
    function setting(string $key, $default = null)
    {
        return PlatformSetting::cached($key, $default);
    }
}

if (! function_exists('settings')) {
    /**
     * Get multiple platform settings
     */
    function settings(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = setting($key);
        }

        return $result;
    }
}
