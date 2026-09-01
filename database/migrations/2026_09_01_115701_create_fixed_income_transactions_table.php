<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_income_transactions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fixed_income_investment_id')
                ->constrained('fixed_income_investments')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('type');

            $table->decimal('amount', 30, 8)->default(0);

            $table->string('currency', 10);

            $table->string('status')->default('completed');

            $table->string('reference')->unique();

            // Links to Xavier's financial transaction/ledger
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('ledger_id')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'fixed_income_investment_id',
                'type'
            ]);

            $table->index('transaction_id');
            $table->index('ledger_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixed_income_transactions');
    }
};
