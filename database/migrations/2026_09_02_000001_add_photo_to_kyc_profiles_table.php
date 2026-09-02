<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('kyc_profiles', 'photo')) {
            Schema::table('kyc_profiles', function (Blueprint $table): void {
                $table->string('photo')->nullable()->after('drivers_license');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kyc_profiles', 'photo')) {
            Schema::table('kyc_profiles', function (Blueprint $table): void {
                $table->dropColumn('photo');
            });
        }
    }
};
