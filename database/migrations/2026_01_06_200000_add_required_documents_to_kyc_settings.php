<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('kyc_settings', 'required_documents')) {
                $table->json('required_documents')->nullable()->after('daily_limit');
            }
        });

        // Backfill sensible defaults for existing tiers
        if (Schema::hasTable('kyc_settings')) {
            DB::table('kyc_settings')->where('tier', 1)->update(['required_documents' => json_encode(['bvn','nin','national_id','proof_of_address'])]);
            DB::table('kyc_settings')->where('tier', 2)->update(['required_documents' => json_encode(['bvn','nin','intl_passport','proof_of_address'])]);
            DB::table('kyc_settings')->where('tier', 3)->update(['required_documents' => json_encode(['bvn','nin','intl_passport','national_id','drivers_license','proof_of_address'])]);
        }
    }

    public function down(): void
    {
        Schema::table('kyc_settings', function (Blueprint $table) {
            if (Schema::hasColumn('kyc_settings', 'required_documents')) {
                $table->dropColumn('required_documents');
            }
        });
    }
};
