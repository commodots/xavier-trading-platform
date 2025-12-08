<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
	{
		Schema::table('orders', function (Blueprint $table) {
			if (!Schema::hasColumn('orders', 'market')) {
				$table->string('market')->default('NGX'); // NGX | GLOBAL | CRYPTO
			}
			if (!Schema::hasColumn('orders', 'currency')) {
				$table->string('currency')->default('NGN'); // NGN | USD
			}
			if (!Schema::hasColumn('orders', 'symbol')) {
				$table->string('symbol')->nullable();
			}
			if (!Schema::hasColumn('orders', 'company')) {
				$table->string('company')->nullable();
			}
			if (!Schema::hasColumn('orders', 'units')) {
				$table->decimal('units', 15, 6)->default(0);
			}
			if (!Schema::hasColumn('orders', 'amount')) {
				$table->decimal('amount', 15, 2);
			}
			if (!Schema::hasColumn('orders', 'market_price')) {
				$table->decimal('market_price', 15, 2);
			}
			if (!Schema::hasColumn('orders', 'status')) {
				$table->string('status')->default('pending_market');  
			}
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
