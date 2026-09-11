<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_accounts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('provider'); // csl, etc.

            $table->string('customer_id')->nullable();
            $table->string('market_id')->nullable();
            $table->string('product_id')->nullable();

            $table->string('market_account_id')->nullable();
            $table->string('market_customer_id')->nullable();

            $table->string('portfolio_id')->nullable();
            $table->string('cash_funding_account_id')->nullable();

            $table->string('status')
                ->default('pending');

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->unique([
                'provider',
                'market_account_id',
            ]);

            $table->index([
                'user_id',
                'provider',
            ]);

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_accounts');
    }
};
