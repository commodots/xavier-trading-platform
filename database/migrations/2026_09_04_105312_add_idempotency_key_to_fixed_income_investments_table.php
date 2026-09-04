<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_income_investments', function (Blueprint $table) {
            $table->string(
                'idempotency_key',
                100
            )->nullable()->unique()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_income_investments', function (Blueprint $table) {
            $table->dropUnique([
                'idempotency_key',
            ]);

            $table->dropColumn(
                'idempotency_key'
            );
        });
    }
};