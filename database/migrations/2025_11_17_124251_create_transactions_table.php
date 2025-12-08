<?php 
// database/migrations/2025_11_05_000003_create_transactions_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('reference')->unique();
            $table->enum('type', ['deposit','withdrawal','trade','fee','transfer']);
            $table->string('asset')->nullable();
            $table->decimal('amount', 30, 8);
            $table->string('status')->default('pending'); // pending, completed, failed
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('transactions');
    }
};
