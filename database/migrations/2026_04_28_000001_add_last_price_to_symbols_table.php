<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('symbols', function (Blueprint $table) {
            $table->decimal('last_price', 12, 4)->nullable()->after('exchange');
            $table->decimal('change', 8, 4)->nullable()->after('last_price');
            $table->decimal('volume', 20, 2)->nullable()->after('change');
        });
    }

    public function down(): void
    {
        Schema::table('symbols', function (Blueprint $table) {
            $table->dropColumn(['last_price', 'change', 'volume']);
        });
    }
};
