<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'provider_cancellation_status')) {
                $table->string('provider_cancellation_status')
                    ->nullable()
                    ->after('reconciliation_status');
            }

            if (! Schema::hasColumn('orders', 'provider_cancel_requested_at')) {
                $table->timestamp('provider_cancel_requested_at')
                    ->nullable()
                    ->after('provider_cancellation_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('orders', 'provider_cancellation_status')) {
                $columns[] = 'provider_cancellation_status';
            }

            if (Schema::hasColumn('orders', 'provider_cancel_requested_at')) {
                $columns[] = 'provider_cancel_requested_at';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
