<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createIndexIfMissing('wallet_transactions', ['user_id', 'created_at'], 'wallet_transactions_user_id_created_at_index');
        $this->createIndexIfMissing('wallet_transactions', ['type', 'created_at'], 'wallet_transactions_type_created_at_index');

        $this->createIndexIfMissing('orders', ['user_id', 'created_at'], 'orders_user_id_created_at_index');
        $this->createIndexIfMissing('orders', ['status', 'created_at'], 'orders_status_created_at_index');
        $this->createIndexIfMissing('orders', ['symbol', 'created_at'], 'orders_symbol_created_at_index');

        $this->createIndexIfMissing('trades', ['created_at'], 'trades_created_at_index');
        $this->createIndexIfMissing('trades', ['order_id', 'created_at'], 'trades_order_id_created_at_index');

        $this->createIndexIfMissing('portfolios', ['user_id'], 'portfolios_user_id_index');
        $this->createIndexIfMissing('portfolios', ['symbol'], 'portfolios_symbol_index');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('wallet_transactions', 'wallet_transactions_user_id_created_at_index');
        $this->dropIndexIfExists('wallet_transactions', 'wallet_transactions_type_created_at_index');

        $this->dropIndexIfExists('orders', 'orders_user_id_created_at_index');
        $this->dropIndexIfExists('orders', 'orders_status_created_at_index');
        $this->dropIndexIfExists('orders', 'orders_symbol_created_at_index');

        $this->dropIndexIfExists('trades', 'trades_order_id_created_at_index');
        $this->dropIndexIfExists('trades', 'trades_created_at_index');

        $this->dropIndexIfExists('portfolios', 'portfolios_user_id_index');
        $this->dropIndexIfExists('portfolios', 'portfolios_symbol_index');
    }

    protected function createIndexIfMissing(string $table, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
            $table->index($columns, $indexName);
        });
    }

    protected function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();
        
        if ($driver === 'sqlite') {
            $result = DB::select("PRAGMA index_list(`{$table}`)");
            foreach ($result as $index) {
                if ($index->name === $indexName) {
                    return true;
                }
            }
            return false;
        }
        
        // MySQL and other drivers
        return count(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName])) > 0;
    }
};