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
    Schema::create('model_portfolio_stocks', function (Blueprint $table) {
        $table->id();
        $table->foreignId('model_portfolio_id')->constrained()->onDelete('cascade');
        $table->string('symbol');
        $table->decimal('allocation_percentage', 5,2);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_portfolio_stocks');
    }
};
