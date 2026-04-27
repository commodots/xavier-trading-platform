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
        Schema::create('symbols', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique(); // e.g., AAPL
            $table->string('name')->nullable();  // e.g., Apple Inc.
            $table->string('type')->nullable();  // e.g., Common Stock
            $table->string('exchange')->nullable();
            $table->timestamps();

            // Indexing for faster search performance
            $table->index('symbol');
            $table->fulltext('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('symbols');
    }
};
