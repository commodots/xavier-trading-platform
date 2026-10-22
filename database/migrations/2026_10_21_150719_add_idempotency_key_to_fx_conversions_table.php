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
        Schema::table('fx_conversions', function (Blueprint $table) {
            $table->string('idempotency_key')->nullable();
            $table->unique(['user_id', 'idempotency_key'], 'fx_conversions_user_idempotency_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fx_conversions', function (Blueprint $table) {
            $table->dropUnique('fx_conversions_user_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
