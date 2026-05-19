<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['subscription_fee', 'wallet_topup', 'adjustment']);
            $table->enum('status', ['pending', 'paid', 'failed'])->default('pending');
            $table->string('reference')->nullable()->unique()->comment('Gateway transaction hash');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_records');
    }
};