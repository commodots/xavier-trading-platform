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
            $table->decimal('charge',15,2)->default(0)->after('currency');
            
            
            $table->decimal('net_amount',15,2)->after('charge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('new_transactions_table', function (Blueprint $table) {
            //
        });
    }
};
