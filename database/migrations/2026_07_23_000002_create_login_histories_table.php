<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->ipAddress('ip_address');
            $table->string('device')->nullable();
            $table->string('browser');
            $table->string('platform');
            $table->string('location')->nullable();
            $table->boolean('successful');
            $table->timestamp('logged_in_at');
            $table->timestamps();

            $table->index('user_id');
            $table->index('ip_address');
            $table->index('successful');
            $table->index('logged_in_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_histories');
    }
};