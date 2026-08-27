<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The expense number is assigned after creation using the database ID
     * (EXP-YYYYMM-000001). The column must be nullable so the record can be
     * created first, then the number derived from its unique ID.
     */
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'expense_no')) {
                $table->string('expense_no')->nullable()->unique()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            if (Schema::hasColumn('expenses', 'expense_no')) {
                $table->string('expense_no')->nullable(false)->change();
            }
        });
    }
};