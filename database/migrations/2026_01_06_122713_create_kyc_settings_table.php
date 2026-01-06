<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kyc_settings', function (Blueprint $table) {
            $table->id();
            $table->integer('tier')->unique(); // 1, 2, or 3
            $table->string('tier_name');       // Basic, Mid, Full
            $table->decimal('daily_limit', 15, 2);
            $table->timestamps();
        });

        DB::table('kyc_settings')->insert([
            ['tier' => 1, 'tier_name' => 'Basic', 'daily_limit' => 50000],
            ['tier' => 2, 'tier_name' => 'Mid', 'daily_limit' => 1000000],
            ['tier' => 3, 'tier_name' => 'Full', 'daily_limit' => 999999999], // Unlimited
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kyc_settings');
    }
};
