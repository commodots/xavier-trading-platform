<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_sync_logs', function (Blueprint $table) {
            $table->id();

            $table->string('provider');

            $table->string('operation');

            $table->string('status');

            $table->string('reference')
                ->nullable();

            $table->text('error_message')
                ->nullable();

            $table->json('request')
                ->nullable();

            $table->json('response')
                ->nullable();

            $table->timestamp('started_at')
                ->nullable();

            $table->timestamp('completed_at')
                ->nullable();

            $table->timestamps();

            $table->index([
                'provider',
                'operation',
            ]);

            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_sync_logs');
    }
};
