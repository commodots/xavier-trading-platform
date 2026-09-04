<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_income_investments', function (Blueprint $table) {
            $table->decimal('reserved_amount', 30, 8)
                ->default(0)
                ->after('principal_amount');

            $table->dateTime('funded_at')
                ->nullable()
                ->after('investment_date');

            $table->dateTime('last_status_at')
                ->nullable()
                ->after('redeemed_at');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_income_investments', function (Blueprint $table) {
            $table->dropColumn([
                'reserved_amount',
                'funded_at',
                'last_status_at',
            ]);
        });
    }
};
