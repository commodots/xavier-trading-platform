<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_portfolio_performance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('metric', 100); // Daily Return, Monthly Return, Annual Return, Sharpe Ratio, Volatility
            $table->decimal('value', 18, 6)->default(0);
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'recorded_at']);
            $table->index(['metric', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_portfolio_performance_logs');
    }
};