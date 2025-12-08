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
		Schema::create('system_settings', function (Blueprint $table) {
			$table->id();
			$table->string('company_name')->nullable();
			$table->string('support_email')->nullable();
			$table->string('base_currency')->default('NGN');
			$table->decimal('trading_fee', 10, 2)->default(0);
			$table->decimal('withdrawal_fee', 10, 2)->default(0);

			$table->string('ngx_api_key')->nullable();
			$table->string('global_api_key')->nullable();
			$table->string('crypto_api_key')->nullable();

			$table->string('paystack_public')->nullable();
			$table->string('paystack_secret')->nullable();
			$table->string('paystack_callback')->nullable();

			$table->boolean('dark_mode')->default(false);
			$table->boolean('maintenance_mode')->default(false);

			$table->timestamps();
		});
	}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
