<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add only if not already present
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->unique();
            }
            if (!Schema::hasColumn('users', 'bvn')) {
                $table->string('bvn', 11)->nullable()->unique();
            }
            if (!Schema::hasColumn('users', 'dob')) {
                $table->date('dob')->nullable();
            }
            if (!Schema::hasColumn('users', 'gender')) {
                $table->string('gender')->nullable();
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->text('address')->nullable();
            }
            if (!Schema::hasColumn('users', 'kyc_status')) {
                $table->enum('kyc_status', ['none','pending','verified','rejected'])->default('none');
            }
            if (!Schema::hasColumn('users', 'kyc_note')) {
                $table->text('kyc_note')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone','bvn','dob','gender','address','kyc_status','kyc_note']);
        });
    }
};
