<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fixed_income_provider_logs', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger(
                'fixed_income_investment_id'
            );

            $table->string('provider')->nullable();

            $table->string('operation');

            $table->string(
                'request_reference'
            )->nullable();

            $table->string(
                'provider_reference'
            )->nullable();

            $table->unsignedSmallInteger(
                'http_status'
            )->nullable();

            $table->string('status');

            $table->json(
                'request_payload'
            )->nullable();

            $table->json(
                'response_payload'
            )->nullable();

            $table->text(
                'error_message'
            )->nullable();

            $table->timestamps();

            $table->index(
                'fixed_income_investment_id'
            );

            $table->index(
                'provider_reference'
            );

            $table->index(
                ['provider', 'status']
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'fixed_income_provider_logs'
        );
    }
};