<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_earnings', function (Blueprint $table) {
            if (!Schema::hasColumn('platform_earnings', 'currency')) {
                $table->string('currency', 8)->default('NGN')->after('amount');
            }
            if (!Schema::hasColumn('platform_earnings', 'amount_ngn')) {
                $table->decimal('amount_ngn', 15, 2)->default(0)->after('currency');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_earnings', function (Blueprint $table) {
            if (Schema::hasColumn('platform_earnings', 'amount_ngn')) {
                $table->dropColumn('amount_ngn');
            }
            if (Schema::hasColumn('platform_earnings', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
