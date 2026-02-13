<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('currency'); // NGN, USD, etc.
            $table->decimal('amount', 18, 2);
            $table->string('type'); // FUND, FX_CONVERSION, FX_MARKUP_PROFIT, BROKER_FUNDING
            $table->string('status')->default('completed'); // pending, completed, failed
            $table->string('reference')->nullable();
            $table->json('meta')->nullable();
            $table->boolean('is_platform')->default(false); // true for platform-level entries
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('ledgers');
    }
};
