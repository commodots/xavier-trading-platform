<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            if (! Schema::hasIndex('trades', 'trades_provider_reference_index')) {
                $table->index(
                    ['provider', 'reference'],
                    'trades_provider_reference_index'
                );
            }

            if (! Schema::hasIndex('trades', 'trades_provider_reference_unique')) {
                $table->unique(
                    ['provider', 'reference'],
                    'trades_provider_reference_unique'
                );
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasIndex('trades', 'trades_provider_reference_unique')) {
            Schema::table('trades', function (Blueprint $table) {
                $table->dropUnique('trades_provider_reference_unique');
            });
        }

        if (Schema::hasIndex('trades', 'trades_provider_reference_index')) {
            Schema::table('trades', function (Blueprint $table) {
                $table->dropIndex('trades_provider_reference_index');
            });
        }
    }
};
