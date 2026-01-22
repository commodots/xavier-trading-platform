<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            if (!Schema::hasColumn('wallets', 'cleared_balance')) {
                $table->decimal('cleared_balance', 18, 2)->default(0)->after('balance');
            }
            if (!Schema::hasColumn('wallets', 'uncleared_balance')) {
                $table->decimal('uncleared_balance', 18, 2)->default(0)->after('cleared_balance');
            }
        });
    }

    public function down(): void
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['cleared_balance', 'uncleared_balance']);
        });
    }
};