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
        Schema::create('portfolio_performance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_portfolio_id')->constrained()->onDelete('cascade');
            $table->decimal('value', 15, 2);
            $table->decimal('return_percentage', 8, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_performance_logs');
    }
};
