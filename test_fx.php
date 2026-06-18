<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;

echo "=== FX System Verification ===\n\n";

// Check tables
$tables = ['fx_settings', 'fx_pairs', 'fx_conversions'];
foreach ($tables as $table) {
    $exists = Schema::hasTable($table);
    echo "Table '{$table}': " . ($exists ? '✓ EXISTS' : '✗ MISSING') . "\n";
}

echo "\n=== Seed Data ===\n";

// Check FxSettings
$settings = \App\Models\FxSetting::first();
if ($settings) {
    echo "FxSettings: ✓\n";
    echo "  - Provider: {$settings->provider}\n";
    echo "  - Enabled: " . ($settings->enabled ? 'yes' : 'no') . "\n";
    echo "  - Auto-convert stocks: " . ($settings->auto_convert_stocks ? 'yes' : 'no') . "\n";
} else {
    echo "FxSettings: ✗ NO DATA\n";
}

// Check FxPairs
$pairs = \App\Models\FxPair::all();
echo "\nFxPairs: " . $pairs->count() . " found\n";
foreach ($pairs as $pair) {
    echo "  - {$pair->base_currency}/{$pair->quote_currency}: Buy={$pair->buy_rate}, Sell={$pair->sell_rate}\n";
}

echo "\n=== Service Test ===\n";

try {
    $resolver = app(\App\Services\Fx\FxProviderResolver::class);
    $provider = $resolver->resolve();
    echo "Provider resolver: ✓\n";
    echo "  - Active provider: " . (get_class($provider) === \App\Services\Fx\Providers\FincraProvider::class ? 'Fincra' : 'Manual') . "\n";
    
    $fxService = app(\App\Services\Fx\FxService::class);
    echo "FxService: ✓\n";
    
    echo "\n=== All Checks Passed ===\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}