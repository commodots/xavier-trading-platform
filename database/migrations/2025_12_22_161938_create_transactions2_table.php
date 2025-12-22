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
        Schema::create('transactions2', function (Blueprint $table) {
            $table->id();
        $table->foreignId('user_id')->constrained();
        $table->enum('type', [
            'deposit', 'withdrawal', 'buy_stock', 'sell_stock', 
            'buy_crypto', 'sell_crypto', 'buy_global', 'sell_global', 
            'currency_change'
        ]);
        $table->decimal('amount', 20, 2);
        $table->string('currency')->default('NGN');
        $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');
        $table->json('meta')->nullable();
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions2');
    }
};
