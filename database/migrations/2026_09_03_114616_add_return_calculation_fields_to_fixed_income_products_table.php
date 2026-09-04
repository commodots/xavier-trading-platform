<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_income_products', function (Blueprint $table) {
            $table->string('calculation_method')
                ->default('simple_interest')
                ->after('interest_frequency');

            $table->string('day_count_basis')
                ->default('actual_365')
                ->after('calculation_method');

            $table->boolean('capitalise_interest')
                ->default(false)
                ->after('day_count_basis');

            $table->string('maturity_payout')
                ->default('wallet')
                ->after('capitalise_interest');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_income_products', function (Blueprint $table) {
            $table->dropColumn([
                'calculation_method',
                'day_count_basis',
                'capitalise_interest',
                'maturity_payout',
            ]);
        });
    }
};
