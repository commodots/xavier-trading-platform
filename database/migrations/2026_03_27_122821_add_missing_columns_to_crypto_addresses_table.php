<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('crypto_addresses', function (Blueprint $table) {
            $table->text('private_key')->nullable();
            $table->text('qr_code_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crypto_addresses', function (Blueprint $table) {
            $table->dropColumn(['private_key', 'qr_code_url']);
        });
    }
};
