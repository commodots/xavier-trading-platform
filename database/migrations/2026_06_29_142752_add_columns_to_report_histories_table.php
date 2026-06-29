<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_histories', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('type');
            $table->string('format');
            $table->string('wallet')->default('all');
            $table->string('period');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('completed');
        });
    }

    public function down(): void
    {
        Schema::table('report_histories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'name', 'type', 'format', 'wallet', 'period', 'start_date', 'end_date', 'status']);
        });
    }
};