<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });
    }

    public function down(): void
    {
        DB::table('orders')
            ->whereNotIn('status', ['open', 'partially_filled', 'filled', 'canceled'])
            ->update(['status' => 'canceled']);

        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['open', 'partially_filled', 'filled', 'canceled'])
                ->default('open')
                ->change();
        });
    }
};
