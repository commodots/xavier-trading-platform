<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_wallets', function (Blueprint $table) {
            // Add missing columns to match live wallets table
            $table->string('currency')->default('NGN')->after('user_id');
            $table->decimal('ngn_cleared', 18, 2)->default(0)->after('currency');
            $table->decimal('ngn_uncleared', 18, 2)->default(0)->after('ngn_cleared');
            $table->decimal('usd_cleared', 18, 2)->default(0)->after('ngn_uncleared');
            $table->decimal('usd_uncleared', 18, 2)->default(0)->after('usd_cleared');
            $table->decimal('locked', 30, 8)->default(0)->after('balance');
            $table->string('status')->default('active')->after('locked');

            // Drop columns that don't exist in live wallets
            $table->dropColumn('equity');
        });
    }

    public function down(): void
    {
        Schema::table('demo_wallets', function (Blueprint $table) {
            $table->dropColumn(['currency', 'ngn_cleared', 'ngn_uncleared', 'usd_cleared', 'usd_uncleared', 'locked', 'status']);
            $table->decimal('equity', 15, 2)->default(0);
        });
    }
};
