<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->decimal('market_value', 18, 2)->default(0);
            $table->decimal('cost_basis', 18, 2)->default(0);
            $table->decimal('cash_balance', 18, 2)->default(0);
            $table->decimal('gain', 18, 2)->default(0);
            $table->decimal('roi', 8, 4)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->date('snapshot_date');
            $table->timestamps();

            $table->index(['user_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_snapshots');
    }
};