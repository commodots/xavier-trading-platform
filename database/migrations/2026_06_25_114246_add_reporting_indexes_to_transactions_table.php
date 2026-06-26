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
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('user_id');
            $table->index('type');
            $table->index('status');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['type']);
            $table->dropIndex(['status']);
            $table->dropIndex(['created_at']);
            $table->dropIndex(['user_id_created_at']);
            $table->dropIndex(['type_created_at']);
            $table->dropIndex(['status_created_at']);
        });
    }
};