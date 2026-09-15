<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('provider_client_reference')
                ->nullable()
                ->after('provider_order_id');

            $table->timestamp('last_reconciled_at')
                ->nullable()
                ->after('provider_submitted_at');

            $table->string('reconciliation_status')
                ->nullable()
                ->after('last_reconciled_at');

            $table->index('provider_client_reference');
            $table->index('reconciliation_status');

            $table->string('provider_cancellation_status')
                ->nullable()
                ->after('reconciliation_status');

            $table->timestamp('provider_cancel_requested_at')
                ->nullable()
                ->after('provider_cancellation_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['provider_client_reference']);
            $table->dropIndex(['reconciliation_status']);
            $table->dropColumn([
                'provider_client_reference',
                'last_reconciled_at',
                'reconciliation_status',
                'provider_cancellation_status',
                'provider_cancel_requested_at',
            ]);
        });
    }
};
