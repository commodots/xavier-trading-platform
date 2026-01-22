<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Migrate existing portfolios: assume all existing holdings are cleared
        DB::table('portfolios')
            ->where('cleared_quantity', 0)
            ->where('uncleared_quantity', 0)
            ->where('quantity', '>', 0)
            ->update([
                'cleared_quantity' => DB::raw('quantity'),
                'uncleared_quantity' => 0
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally reverse, but since it's data fix, maybe not needed
    }
};
