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
        Schema::table('new_transactions_table', function (Blueprint $table) {
            $table->string('provider_reference')->nullable()->unique();
            $table->string('idempotency_key')->nullable();
            $table->unique(['user_id', 'idempotency_key'], 'new_transactions_user_idempotency_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('new_transactions_table', function (Blueprint $table) {
            $table->dropUnique('new_transactions_user_idempotency_unique');
            $table->dropUnique(['provider_reference']);
            $table->dropColumn(['provider_reference', 'idempotency_key']);
        });
    }
};
