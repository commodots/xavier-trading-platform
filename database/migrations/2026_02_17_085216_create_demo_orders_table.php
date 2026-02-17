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
        Schema::create('demo_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('symbol'); // stock symbol
            $table->enum('market_type', ['local', 'international']);
            $table->enum('type', ['buy', 'sell']);
            $table->integer('quantity');
            $table->decimal('price', 15, 4); // price per unit
            $table->decimal('total', 15, 2); // quantity * price
            $table->enum('status', ['open', 'closed'])->default('closed'); // demo executes immediately
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_orders');
    }
};
