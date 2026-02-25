<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('demo_orders', function (Blueprint $table) {
            DB::statement("ALTER TABLE demo_orders MODIFY COLUMN market_type VARCHAR(50)");
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demo_orders', function (Blueprint $table) {
            DB::statement("ALTER TABLE demo_orders MODIFY COLUMN market_type ENUM('local', 'international')");
        });
    }
};
