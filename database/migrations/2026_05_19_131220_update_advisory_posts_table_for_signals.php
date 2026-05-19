<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('advisory_posts', function (Blueprint $table) {
            
            $table->string('asset_symbol')->nullable()->after('content');
            
            $table->enum('recommendation', ['BUY', 'SELL', 'HOLD'])->default('HOLD')->after('asset_symbol');
            
           
            $table->enum('tier', ['free', 'pro', 'premium'])->default('free')->after('recommendation');
        });
    }

    public function down(): void
    {
        Schema::table('advisory_posts', function (Blueprint $table) {
            $table->dropColumn(['asset_symbol', 'recommendation', 'tier']);
        });
    }
};