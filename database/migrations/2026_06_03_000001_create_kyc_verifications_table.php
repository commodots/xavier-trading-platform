<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kyc_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('verification_type'); // bvn | nin | selfie
            $table->string('verification_id')->nullable();
            $table->string('status')->default('pending'); // pending | approved | failed
            $table->json('response_json')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'verification_type']);
            $table->index(['user_id', 'status']);
        });

        // verification_level: 0=registered, 1=email verified, 2=BVN+NIN, 3=face verified
        if (!Schema::hasColumn('users', 'verification_level')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedTinyInteger('verification_level')->default(0)->after('kyc_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kyc_verifications');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('verification_level');
        });
    }
};
