<?php 
// database/migrations/2025_11_05_000002_create_orders_trades_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('symbol'); // e.g. ZENITH, AAPL, BTCUSD
            $table->enum('side', ['buy','sell']);
            $table->enum('type', ['market','limit'])->default('limit');
            $table->decimal('price', 28, 8)->nullable(); // null for market
            $table->decimal('quantity', 28, 8);
            $table->decimal('filled_quantity', 28, 8)->default(0);
            $table->enum('status', ['open','partially_filled','filled','canceled'])->default('open');
            $table->string('source')->nullable();
            $table->timestamps();
        });

        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->foreignId('counterparty_order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->decimal('price', 28, 8);
            $table->decimal('quantity', 28, 8);
            $table->decimal('fee', 28, 8)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('trades');
        Schema::dropIfExists('orders');
    }
};
