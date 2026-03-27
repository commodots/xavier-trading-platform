<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            if (! Schema::hasColumn('trades', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            }
            if (! Schema::hasColumn('trades', 'pair')) {
                $table->string('pair', 20)->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('trades', 'type')) {
                $table->string('type', 10)->nullable()->after('pair');
            }
            if (! Schema::hasColumn('trades', 'amount')) {
                $table->decimal('amount', 30, 8)->nullable()->after('type');
            }
            if (! Schema::hasColumn('trades', 'entry_price')) {
                $table->decimal('entry_price', 30, 8)->nullable()->after('amount');
            }
            if (! Schema::hasColumn('trades', 'exit_price')) {
                $table->decimal('exit_price', 30, 8)->nullable()->after('entry_price');
            }
            if (! Schema::hasColumn('trades', 'profit_loss')) {
                $table->decimal('profit_loss', 30, 8)->nullable()->after('exit_price');
            }
            if (! Schema::hasColumn('trades', 'status')) {
                $table->string('status', 20)->default('open')->after('profit_loss');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropColumn(['user_id', 'pair', 'type', 'amount', 'entry_price', 'exit_price', 'profit_loss', 'status']);
        });
    }
};
