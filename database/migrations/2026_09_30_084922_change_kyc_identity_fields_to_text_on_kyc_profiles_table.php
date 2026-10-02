<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->text('bvn')->nullable()->change();
            $table->text('nin')->nullable()->change();
            $table->text('tin')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('kyc_profiles', function (Blueprint $table) {
            $table->string('bvn')->nullable()->change();
            $table->string('nin')->nullable()->change();
            $table->string('tin')->nullable()->change();
        });
    }
};
