<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('alpaca_order_id')->nullable()->after('id')->index();
            $table->decimal('limit_price', 15, 2)->nullable();
            $table->decimal('stop_price', 15, 2)->nullable();
            $table->decimal('take_profit', 15, 2)->nullable();
            $table->decimal('stop_loss', 15, 2)->nullable();
            $table->string('type')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['alpaca_order_id', 'limit_price', 'stop_price', 'take_profit', 'stop_loss']);
        });
    }
};
