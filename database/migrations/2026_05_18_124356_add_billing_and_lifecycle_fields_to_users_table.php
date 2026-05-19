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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('subscription_status', ['trial', 'active', 'inactive', 'suspended'])->default('trial')->after('email');
            $table->timestamp('trial_ends_at')->nullable()->after('subscription_status');
            $table->timestamp('last_active_at')->nullable()->after('trial_ends_at');
            $table->decimal('wallet_balance', 15, 2)->default(0)->after('last_active_at');
            $table->decimal('wallet_debt', 15, 2)->default(0)->after('wallet_balance');
            $table->timestamp('last_fee_charged_at')->nullable()->after('wallet_debt');
            $table->timestamp('next_fee_due_at')->nullable()->after('last_fee_charged_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'subscription_status', 
                'trial_ends_at', 
                'last_active_at', 
                'wallet_balance', 
                'wallet_debt', 
                'last_fee_charged_at', 
                'next_fee_due_at'
            ]);
        });
    }
};
