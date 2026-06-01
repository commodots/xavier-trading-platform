<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kyc_profiles') && ! Schema::hasColumn('kyc_profiles', 'verified_at')) {
            Schema::table('kyc_profiles', function (Blueprint $table) {
                $table->timestamp('verified_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('kyc_profiles') && Schema::hasColumn('kyc_profiles', 'verified_at')) {
            Schema::table('kyc_profiles', function (Blueprint $table) {
                $table->dropColumn('verified_at');
            });
        }
    }
};
