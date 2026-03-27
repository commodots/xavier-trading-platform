<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('system_settings', 'crypto_spread')) {
                $table->decimal('crypto_spread', 10, 2)->default(0)->after('trading_fee');
            }
            if (! Schema::hasColumn('system_settings', 'crypto_fee')) {
                $table->decimal('crypto_fee', 10, 2)->default(0)->after('crypto_spread');
            }
            if (! Schema::hasColumn('system_settings', 'max_trade_amount')) {
                $table->decimal('max_trade_amount', 30, 8)->default(10000)->after('crypto_fee');
            }
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['crypto_spread', 'crypto_fee', 'max_trade_amount']);
        });
    }
};
