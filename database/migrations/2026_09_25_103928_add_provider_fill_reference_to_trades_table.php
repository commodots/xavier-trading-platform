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
        Schema::table('trades', function (Blueprint $table) {
            $table->string('provider_fill_reference')
                ->nullable()
                ->after('provider_trade_id');

            $table->index([
                'provider',
                'provider_fill_reference',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropIndex([
                'trades_provider_provider_fill_reference_index',
            ]);
            $table->dropColumn('provider_fill_reference');
        });
    }
};
