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
        Schema::create('fx_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('manual');
            $table->boolean('enabled')->default(true);
            $table->boolean('auto_convert_stocks')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fx_settings');
    }
};
