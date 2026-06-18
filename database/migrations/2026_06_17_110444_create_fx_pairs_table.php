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
        Schema::create('fx_pairs', function (Blueprint $table) {
            $table->id();
            $table->string('base_currency');
            $table->string('quote_currency');
            $table->decimal('buy_rate', 18, 6);
            $table->decimal('sell_rate', 18, 6);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fx_pairs');
    }
};
