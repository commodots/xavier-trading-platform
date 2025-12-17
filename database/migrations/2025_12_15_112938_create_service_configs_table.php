<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_configs', function (Blueprint $table) {
		$table->id();
		$table->string('service'); 
		$table->enum('type', ['ngx', 'crypto', 'stocks', 'fx', 'cscs', 'payment']);
		$table->enum('mode', ['live', 'test', 'dummy'])->default('dummy');

		$table->string('base_url')->nullable();
		$table->json('headers')->nullable();
		$table->json('params')->nullable();
		$table->json('credentials')->nullable();

		$table->boolean('is_active')->default(false);
		$table->timestamps();
	});


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_configs');
    }
};
