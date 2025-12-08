<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->after('id');
            }
            if (!Schema::hasColumn('users', 'surname')) {
                $table->string('surname')->after('first_name');
            }
            if (!Schema::hasColumn('users', 'mobile')) {
                $table->string('mobile')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'dob')) {
                $table->date('dob')->nullable()->after('mobile');
            }
            if (!Schema::hasColumn('users', 'bvn')) {
                $table->string('bvn')->nullable()->after('dob');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'surname', 'mobile', 'dob', 'bvn']);
        });
    }
};
