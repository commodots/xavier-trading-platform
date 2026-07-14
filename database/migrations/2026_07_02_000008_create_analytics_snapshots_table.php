<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 100); // Total Users, Total Wallet Balance, AssetsUnderManagement, Revenue, Active Investors, Daily Trades
            $table->decimal('value', 18, 2)->default(0);
            $table->date('snapshot_date');
            $table->timestamps();

            $table->index(['metric', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_snapshots');
    }
};