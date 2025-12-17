<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {

            if (!Schema::hasColumn('trades', 'settlement_status')) {
                $table->enum('settlement_status', ['pending','settled','failed'])->default('pending');
            }

            if (!Schema::hasColumn('trades', 'settlement_date')) {
                $table->date('settlement_date')->nullable();
            }

            if (!Schema::hasColumn('trades', 'reference')) {
                $table->string('reference')->nullable();
            }

        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropColumn([
                'settlement_status',
                'settlement_date',
                'reference'
            ]);
        });
    }
};
