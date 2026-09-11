<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {

            $table->string('provider')
                ->nullable()
                ->after('reference');

            $table->string('provider_trade_id')
                ->nullable()
                ->after('provider');

            $table->string('provider_order_id')
                ->nullable()
                ->after('provider_trade_id');

            $table->json('provider_response')
                ->nullable()
                ->after('provider_order_id');

            $table->timestamp('provider_executed_at')
                ->nullable()
                ->after('provider_response');

            $table->index([
                'provider',
                'provider_trade_id',
            ]);

            $table->index('provider_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {

            $table->dropIndex([
                'provider',
                'provider_trade_id',
            ]);

            $table->dropIndex([
                'provider_order_id',
            ]);

            $table->dropColumn([
                'provider',
                'provider_trade_id',
                'provider_order_id',
                'provider_response',
                'provider_executed_at',
            ]);
        });
    }
};
