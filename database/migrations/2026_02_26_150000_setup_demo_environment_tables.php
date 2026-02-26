<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {

    //Fix Foreign Keys on Demo Trades & Settlements
    Schema::table('demo_trades', function (Blueprint $table) {
      $table->dropForeign(['order_id']);
      $table->dropForeign(['counterparty_order_id']);

      $table->foreign('order_id')->references('id')->on('demo_orders')->onDelete('cascade');
      $table->foreign('counterparty_order_id')->references('id')->on('demo_orders')->onDelete('set null');
    });

    Schema::table('demo_settlements', function (Blueprint $table) {
      $table->dropForeign(['trade_id']);
      $table->foreign('trade_id')->references('id')->on('demo_trades')->onDelete('cascade');
    });
  }

  public function down(): void
  {

    Schema::table('demo_trades', function (Blueprint $table) {
      $table->dropForeign(['order_id']);
      $table->dropForeign(['counterparty_order_id']);

      $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
      $table->foreign('counterparty_order_id')->references('id')->on('orders')->onDelete('set null');
    });

    Schema::table('demo_settlements', function (Blueprint $table) {
      $table->dropForeign(['trade_id']);
      $table->foreign('trade_id')->references('id')->on('trades')->onDelete('cascade');
    });
  }
};
