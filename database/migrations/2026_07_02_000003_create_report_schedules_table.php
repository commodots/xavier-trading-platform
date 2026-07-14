<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('report_name');
            $table->string('frequency'); // daily, weekly, monthly
            $table->json('filters')->nullable();
            $table->json('email_to')->nullable();
            $table->timestamp('last_run')->nullable();
            $table->timestamp('next_run')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_schedules');
    }
};