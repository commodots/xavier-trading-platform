<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('fx_config', function (Blueprint $table) {
            $table->id();
            $table->decimal('min_markup', 5, 2)->default(1);
            $table->decimal('max_markup', 5, 2)->default(5);
            $table->decimal('target_margin_percent', 5, 2)->default(2);
            $table->decimal('volatility_threshold', 5, 2)->default(3);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('fx_config');
    }
};
