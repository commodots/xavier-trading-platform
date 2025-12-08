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
		Schema::create('kycs', function (Blueprint $table) {
			$table->id();
			$table->foreignId('user_id')->constrained()->onDelete('cascade');
			$table->string('id_type')->nullable();
			$table->string('id_value')->nullable();
			$table->string('id_front')->nullable();
			$table->string('id_back')->nullable();
			$table->string('proof')->nullable();
			$table->string('status')->default('pending');
			$table->timestamps();
		});
	}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kycs');
    }
};
