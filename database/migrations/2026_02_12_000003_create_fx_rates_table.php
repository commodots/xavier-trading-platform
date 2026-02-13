<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('fx_rates', function (Blueprint $table) {
            $table->id();
            $table->string('from_currency');
            $table->string('to_currency');
            $table->decimal('base_rate', 18, 6);
            $table->decimal('markup_percent', 5, 2)->default(0);
            $table->decimal('effective_rate', 18, 6);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('fx_rates');
    }
};
