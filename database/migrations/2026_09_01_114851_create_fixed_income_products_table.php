<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_income_products', function (Blueprint $table) {
            $table->id();

            // Basic product information
            $table->string('name');
            $table->string('code')->unique();
            $table->string('type')->default('bond');
            $table->text('description')->nullable();

            // Currency / issuer
            $table->string('currency', 10)->default('NGN');
            $table->string('issuer')->nullable();

            // Product status
            $table->string('status')->default('draft');
            
            // Investment limits
            $table->decimal('minimum_amount', 30, 8)->default(0);
            $table->decimal('maximum_amount', 30, 8)->nullable();
            $table->boolean('maximum_open_ended')->default(false);

            // Investment availability
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('open_ended')->default(false);

            // Return configuration
            $table->decimal('interest_rate', 12, 6)->nullable();
            $table->string('rate_type')->default('fixed');
            $table->string('interest_frequency')->default('at_maturity');

            // Tenor
            $table->integer('tenor_days')->nullable();

            // Lock / redemption
            $table->boolean('early_withdrawal_allowed')->default(false);
            $table->decimal('early_withdrawal_penalty', 12, 6)->default(0);

            // Fees
            $table->decimal('subscription_fee', 12, 6)->default(0);
            $table->string('subscription_fee_type')->default('none');

            // Capacity
            $table->decimal('maximum_capacity', 30, 8)->nullable();

            // Execution configuration
            $table->string('execution_mode')->default('manual');
            $table->string('provider')->nullable();

            // Reinvestment
            $table->boolean('allow_reinvestment')->default(false);

            // Additional configurable information
            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['status', 'currency']);
            $table->index(['start_date', 'end_date']);
            $table->index('execution_mode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_income_products');
    }
};