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
    Schema::create('model_portfolios', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->text('description');
        $table->enum('risk_profile', ['conservative','balanced','aggressive']);
        $table->boolean('is_premium')->default(true);
        $table->decimal('starting_value', 15,2)->default(100000);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_portfolios');
    }
};
