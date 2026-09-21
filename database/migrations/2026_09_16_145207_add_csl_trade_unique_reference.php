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
        Schema::table('trades', function (Blueprint $table) {

            $table->unique(
                ['provider', 'reference'],
                'trades_provider_reference_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('trades', 'trades_provider_reference_unique')) {
            Schema::table('trades', function (Blueprint $table) {
                $table->dropUnique('trades_provider_reference_unique');
            });
        }
    }
};
