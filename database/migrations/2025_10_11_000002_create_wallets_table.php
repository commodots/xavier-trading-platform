<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('currency', 10)->default(env('DEFAULT_WALLET_CURRENCY','NGN'));
            $table->decimal('balance', 30, 8)->default(0);
            $table->decimal('locked', 30, 8)->default(0);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['user_id','currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
