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
		Schema::table('wallets', function (Blueprint $table) {
			if (!Schema::hasColumn('wallets', 'currency')) {
				$table->string('currency')->default('NGN')->after('user_id');
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
