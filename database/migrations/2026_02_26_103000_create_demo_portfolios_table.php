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
        Schema::create('demo_portfolios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('symbol');
            $table->string('name')->nullable();
            $table->string('category'); 
            $table->decimal('quantity', 18, 8)->default(0);
            $table->integer('cleared_quantity')->default(0);
            $table->integer('uncleared_quantity')->default(0);
            $table->decimal('avg_price', 15, 2)->default(0);
            $table->decimal('market_price', 15, 2)->default(0);
            $table->string('currency')->default('NGN');
            $table->timestamps();

            $table->unique(['user_id', 'symbol']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_portfolios');
    }
};
