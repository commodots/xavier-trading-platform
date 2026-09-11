<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->string('provider')
                ->nullable()
                ->after('source');

            $table->string('provider_order_id')
                ->nullable()
                ->after('provider');

            $table->string('provider_market_account_id')
                ->nullable()
                ->after('provider_order_id');

            $table->string('time_in_force')
                ->nullable()
                ->after('provider_market_account_id');

            $table->date('expiry_date')
                ->nullable()
                ->after('time_in_force');

            $table->json('provider_request')
                ->nullable()
                ->after('expiry_date');

            $table->json('provider_response')
                ->nullable()
                ->after('provider_request');

            $table->timestamp('provider_submitted_at')
                ->nullable()
                ->after('provider_response');

            $table->index([
                'provider',
                'provider_order_id',
            ]);

            $table->index('provider_market_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            $table->dropIndex([
                'provider',
                'provider_order_id',
            ]);

            $table->dropIndex([
                'provider_market_account_id',
            ]);

            $table->dropColumn([
                'provider',
                'provider_order_id',
                'provider_market_account_id',
                'time_in_force',
                'expiry_date',
                'provider_request',
                'provider_response',
                'provider_submitted_at',
            ]);
        });
    }
};
