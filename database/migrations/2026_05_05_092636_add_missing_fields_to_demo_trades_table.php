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
        Schema::table('demo_trades', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('pair');
            $table->enum('type', ['buy', 'sell']);
            $table->decimal('amount', 28, 8);
            $table->decimal('entry_price', 28, 8);
            $table->decimal('exit_price', 28, 8)->nullable();
            $table->decimal('profit_loss', 28, 8)->default(0);
            $table->enum('status', ['open', 'closed', 'cancelled'])->default('open');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demo_trades', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'pair', 'type', 'amount', 'entry_price', 'exit_price', 'profit_loss', 'status']);
        });
    }
};
