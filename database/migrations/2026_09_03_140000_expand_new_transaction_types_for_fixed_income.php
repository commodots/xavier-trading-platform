<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE `new_transactions_table` MODIFY COLUMN `type` VARCHAR(64) NOT NULL'
            );

            return;
        }

        Schema::table('new_transactions_table', function (Blueprint $table) {
            $table->string('type', 64)->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                "ALTER TABLE `new_transactions_table` MODIFY COLUMN `type` ENUM('deposit','withdrawal','buy_stock','sell_stock','buy_crypto','sell_crypto','buy_global','sell_global','currency_change') NOT NULL"
            );

            return;
        }

        Schema::table('new_transactions_table', function (Blueprint $table) {
            $table->enum('type', [
                'deposit',
                'withdrawal',
                'buy_stock',
                'sell_stock',
                'buy_crypto',
                'sell_crypto',
                'buy_global',
                'sell_global',
                'currency_change',
            ])->change();
        });
    }
};
