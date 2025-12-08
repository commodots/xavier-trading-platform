<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->change();
            $table->string('surname')->nullable()->change();
            $table->string('mobile')->nullable()->change();
            $table->date('dob')->nullable()->change();
            $table->string('bvn')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
            $table->string('surname')->nullable(false)->change();
            $table->string('mobile')->nullable(false)->change();
            $table->date('dob')->nullable(false)->change();
            $table->string('bvn')->nullable(false)->change();
        });
    }
};
