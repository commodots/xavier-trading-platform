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
    Schema::create('advisory_posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content');
        $table->enum('market_type', ['local','international', 'crypto']);
        $table->enum('risk_level', ['low','medium','high']);
        $table->boolean('is_premium')->default(true);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advisory_posts');
    }
};
