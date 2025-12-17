<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {

            if (!Schema::hasColumn('orders', 'market')) {
                $table->string('market')->default('NGX'); // NGX | GLOBAL | CRYPTO
            }

            if (!Schema::hasColumn('orders', 'currency')) {
                $table->string('currency')->default('NGN');
            }

            if (!Schema::hasColumn('orders', 'service_mode')) {
                $table->enum('service_mode', ['live','test','dummy'])->default('test');
            }

            if (!Schema::hasColumn('orders', 'matched_at')) {
                $table->timestamp('matched_at')->nullable();
            }

            if (!Schema::hasColumn('orders', 'settled_at')) {
                $table->timestamp('settled_at')->nullable();
            }

            if (!Schema::hasColumn('orders', 'adapter')) {
                $table->string('adapter')->nullable(); // NGX_FIX | SIMULATOR
            }

        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'market',
                'currency',
                'service_mode',
                'matched_at',
                'settled_at',
                'adapter'
            ]);
        });
    }
};
