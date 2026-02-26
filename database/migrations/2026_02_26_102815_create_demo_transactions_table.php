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
        Schema::create('demo_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
             $table->enum('type', [
            'deposit', 'withdrawal', 'buy_stock', 'sell_stock', 
            'buy_crypto', 'sell_crypto', 'buy_global', 'sell_global', 
            'currency_change'
        ]);
            $table->decimal('amount', 20, 2);
            $table->string('currency')->default('NGN');
            $table->decimal('charge', 15, 2)->nullable()->default(0);
            $table->decimal('net_amount', 15, 2)->nullable();
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
            $table->boolean('is_cleared')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_transactions');
    }
};
