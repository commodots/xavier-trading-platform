<?php

namespace App\Services\Fx;

use App\Models\FxSetting;
use App\Services\Fx\Providers\FincraProvider;
use App\Services\Fx\Providers\ManualProvider;

class FxProviderResolver
{
    public function resolve(): mixed
    {
        $setting = FxSetting::first();

        if (! $setting || ! $setting->enabled) {
            return app(ManualProvider::class);
        }

        $provider = $setting->provider;

        return match ($provider) {
            'fincra' => app(FincraProvider::class),
            default => app(ManualProvider::class),
        };
    }
}
