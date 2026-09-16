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
        Schema::table('provider_sync_logs', function (Blueprint $table) {

            $table->string('entity_type')
                ->nullable()
                ->after('operation');

            $table->unsignedBigInteger('entity_id')
                ->nullable()
                ->after('entity_type');

            $table->string('severity')
                ->nullable()
                ->after('status');

            $table->json('metadata')
                ->nullable()
                ->after('response');

            $table->index([
                'provider',
                'entity_type',
                'entity_id',
            ]);

            $table->index('severity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('provider_sync_logs', function (Blueprint $table) {
            //
        });
    }
};
