<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('new_transactions_table', function (Blueprint $table) {
            if (! Schema::hasColumn('new_transactions_table', 'tx_hash')) {
                $table->text('tx_hash')->nullable()->after('status');
            }
            if (! Schema::hasColumn('new_transactions_table', 'confirmations')) {
                $table->integer('confirmations')->default(0)->after('tx_hash');
            }
        });
    }

    public function down(): void
    {
        Schema::table('new_transactions_table', function (Blueprint $table) {
            $table->dropColumn(['tx_hash', 'confirmations']);
        });
    }
};
