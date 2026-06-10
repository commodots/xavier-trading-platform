<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->string('bvn_last4', 4)->nullable()->after('bvn');
            $table->string('nin_last4', 4)->nullable()->after('nin');
        });
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->dropColumn(['bvn_last4', 'nin_last4']);
        });
    }
};