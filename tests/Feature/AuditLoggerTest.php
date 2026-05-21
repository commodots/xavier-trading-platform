<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Models\AuditLog;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logger_writes_to_audit_logs_table(): void
    {
        $user = User::factory()->create();

        AuditLogger::log('withdrawal_created', [
            'user_id'   => $user->id,
            'amount'    => 5000,
            'currency'  => 'NGN',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action'  => 'withdrawal_created',
        ]);
    }

    public function test_audit_logger_stores_payload_without_reserved_keys(): void
    {
        $user = User::factory()->create();

        AuditLogger::log('trade_executed', [
            'user_id'   => $user->id,
            'symbol'    => 'AAPL',
            'quantity'  => 10,
            'ip'        => '127.0.0.1',
        ]);

        $record = AuditLog::where('user_id', $user->id)->where('action', 'trade_executed')->first();

        $this->assertNotNull($record);
        // Reserved keys (user_id, ip) must not appear in payload
        $this->assertArrayNotHasKey('user_id', $record->payload ?? []);
        $this->assertArrayNotHasKey('ip', $record->payload ?? []);
        // Business data must be in payload
        $this->assertEquals('AAPL', $record->payload['symbol']);
        $this->assertEquals(10, $record->payload['quantity']);
    }

    public function test_audit_logger_works_with_null_user_id(): void
    {
        AuditLogger::log('system_event', ['detail' => 'cron ran']);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => null,
            'action'  => 'system_event',
        ]);
    }

    public function test_audit_logger_writes_to_audit_log_file(): void
    {
        Log::shouldReceive('channel')
            ->with('audit')
            ->once()
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with('password_changed', \Mockery::any());

        AuditLogger::log('password_changed', ['user_id' => 1]);
    }

    public function test_activity_log_static_helper_creates_record(): void
    {
        $user = User::factory()->create();

        ActivityLog::log($user->id, 'Login', ['note' => 'test']);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'  => $user->id,
            'activity' => 'Login',
        ]);
    }

    public function test_activity_log_accepts_null_user_id(): void
    {
        // Should not throw — user_id is now nullable
        ActivityLog::log(null, 'Failed Login', ['email' => 'x@x.com']);

        $this->assertDatabaseHas('activity_logs', [
            'user_id'  => null,
            'activity' => 'Failed Login',
        ]);
    }
}
