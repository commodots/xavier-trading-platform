<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('symbols', function (Blueprint $table) {

            $table->string('provider')
                ->nullable()
                ->after('exchange');

            $table->string('provider_symbol_id')
                ->nullable()
                ->after('provider');

            $table->string('market_id')
                ->nullable()
                ->after('provider_symbol_id');

            $table->string('product_id')
                ->nullable()
                ->after('market_id');

            $table->string('isin')
                ->nullable()
                ->after('product_id');

            $table->string('provider_symbol_type')
                ->nullable()
                ->after('isin');

            $table->json('provider_metadata')
                ->nullable()
                ->after('provider_symbol_type');

            $table->index([
                'provider',
                'provider_symbol_id',
            ]);

            $table->index('isin');
        });
    }

    public function down(): void
    {
        Schema::table('symbols', function (Blueprint $table) {
            $table->dropIndex([
                'provider',
                'provider_symbol_id',
            ]);

            $table->dropIndex(['isin']);

            $table->dropColumn([
                'provider',
                'provider_symbol_id',
                'market_id',
                'product_id',
                'isin',
                'provider_symbol_type',
                'provider_metadata',
            ]);
        });
    }
};
