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
        Schema::table('notifications', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id') ->constrained()->onDelete('cascade');
            $table->string('title')->nullable();
            $table->text('message');
            $table->string('action')->nullable(); 
            $table->string('action_url')->nullable(); 
            $table->string('icon')->nullable(); 
            $table->json('metadata')->nullable(); // Additional data

            $table->index(['user_id', 'read_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
