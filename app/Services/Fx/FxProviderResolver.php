<?php

namespace App\Services\Fx;

use App\Models\FxSetting;

class FxProviderResolver
{
    public function resolve(): mixed
    {
        $setting = FxSetting::first();

        if (!$setting || !$setting->enabled) {
            return app(\App\Services\Fx\Providers\ManualProvider::class);
        }

        $provider = $setting->provider;

        return match ($provider) {
            'fincra' => app(\App\Services\Fx\Providers\FincraProvider::class),
            default => app(\App\Services\Fx\Providers\ManualProvider::class),
        };
    }
}