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
        Schema::table('new_transactions_table', function (Blueprint $table) {
        // Allow these to be empty initially so the Service can calculate them later
        $table->decimal('charge', 15, 2)->nullable()->change();
        $table->decimal('net_amount', 15, 2)->nullable()->change();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
