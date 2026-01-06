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
        Schema::table('kyc_profiles', function (Blueprint $table) {
        // Change 'level' to store labels: 'Starter', 'Basic', 'Pro', 'Ultimate'
        $table->integer('tier')->default(0)->after('user_id'); 
        $table->decimal('daily_limit', 15, 2)->default(0)->after('level'); 
        
        // New Document Slots
        $table->string('tin')->nullable()->after('nin');
        $table->string('intl_passport')->nullable()->after('tin');
        $table->string('national_id')->nullable()->after('intl_passport');
        $table->string('drivers_license')->nullable()->after('national_id');
        $table->string('proof_of_address')->nullable()->after('drivers_license'); 
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
