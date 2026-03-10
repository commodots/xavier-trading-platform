<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demo_orders', function (Blueprint $table) {
            // This was changed just for testing purposes
            $table->string('market_type', 50)->change();
        });
    }

    public function down(): void
    {
        Schema::table('demo_orders', function (Blueprint $table) {
            //  SQLite doesn't support ENUM 
            // so we use the driver check here 
            if (DB::getDriverName() === 'sqlite') {
                $table->string('market_type')->change();
            } else {
                // MySQL will execute this
                $table->enum('market_type', ['local', 'international'])->change();
            }
        });
    }
};