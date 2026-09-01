<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_income_investments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('fixed_income_product_id')
                ->constrained('fixed_income_products')
                ->restrictOnDelete();

            // Xavier reference
            $table->string('reference')->unique();

            // Investment amount
            $table->decimal('principal_amount', 30, 8);
            $table->string('currency', 10);

            // Rate locked at investment time
            $table->decimal('interest_rate', 12, 6)->nullable();
            $table->string('rate_type')->nullable();

            // Calculated amounts
            $table->decimal('expected_interest', 30, 8)->default(0);
            $table->decimal('expected_maturity_amount', 30, 8)->default(0);

            // Actual amounts after execution/maturity
            $table->decimal('actual_interest', 30, 8)->nullable();
            $table->decimal('actual_maturity_amount', 30, 8)->nullable();

            // Lifecycle
            $table->string('status')->default('pending');

            // Dates
            $table->dateTime('investment_date')->nullable();
            $table->dateTime('execution_date')->nullable();
            $table->dateTime('maturity_date')->nullable();
            $table->dateTime('redeemed_at')->nullable();

            // Funding
            $table->string('funding_method')->nullable();

            // Execution
            $table->string('execution_mode')->default('manual');
            $table->string('provider')->nullable();
            $table->string('provider_reference')->nullable();

            // Reinvestment
            $table->boolean('reinvestment_enabled')->default(false);

            // Additional information
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['fixed_income_product_id', 'status']);
            $table->index('maturity_date');
            $table->index('provider_reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_income_investments');
    }
};