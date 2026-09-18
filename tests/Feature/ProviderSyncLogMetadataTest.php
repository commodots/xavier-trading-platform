<?php

namespace Tests\Feature;

use App\Models\ProviderSyncLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderSyncLogMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_sync_log_tracks_entity_context_and_metadata(): void
    {
        $log = ProviderSyncLog::create([
            'provider' => 'csl',
            'operation' => 'instrument_sync',
            'status' => 'success',
            'entity_type' => 'symbol',
            'entity_id' => 42,
            'severity' => 'info',
            'metadata' => [
                'symbol' => 'TIP',
                'market_id' => 'NGX',
            ],
        ]);

        $this->assertSame('symbol', $log->entity_type);
        $this->assertSame(42, $log->entity_id);
        $this->assertSame('info', $log->severity);
        $this->assertSame('TIP', $log->metadata['symbol']);
    }
}
