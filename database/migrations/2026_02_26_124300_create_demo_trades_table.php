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
        Schema::create('demo_trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('counterparty_order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->decimal('price', 28, 8);
            $table->decimal('quantity', 28, 8);
            $table->decimal('fee', 28, 8)->default(0);
            $table->enum('settlement_status', ['pending', 'settled', 'failed'])->default('pending');
            $table->date('settlement_date')->nullable();
            $table->string('reference')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_trades');
    }
};
