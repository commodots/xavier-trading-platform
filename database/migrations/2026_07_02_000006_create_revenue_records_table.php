<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_records', function (Blueprint $table) {
            $table->id();
            $table->string('source', 100); // Trading Fee, Withdrawal Fee, Subscription, FX Conversion, Broker Commission, Interest, Referral Fee
            $table->foreignId('transaction_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('currency', 10)->default('USD');
            $table->decimal('amount', 18, 2)->default(0);
            $table->decimal('fee_percentage', 8, 4)->nullable();
            $table->text('description')->nullable();
            $table->date('record_date');
            $table->timestamps();

            $table->index(['source', 'record_date']);
            $table->index('record_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_records');
    }
};