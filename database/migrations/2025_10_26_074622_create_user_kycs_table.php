<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_kycs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('id_type')->nullable();
            $table->string('id_value')->nullable();
            $table->string('bvn')->nullable();
            $table->string('nin')->nullable();
            $table->string('status')->default('pending');
            $table->json('meta')->nullable(); // to store provider response or extra info
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_kycs');
    }
};
