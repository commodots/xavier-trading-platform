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
        Schema::create('stock_predictions', function (Blueprint $table) {
            $table->id();
            $table->string('symbol'); // e.g., AAPL
            $table->date('prediction_date');
            $table->decimal('predicted_price', 15, 2);
            $table->decimal('confidence_score', 5, 4); // e.g., 0.8250 for 82.5%
            $table->string('model_version')->default('v1.0');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_predictions');
    }
};
