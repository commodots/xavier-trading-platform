<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        //Rename existing columns to match live table
        Schema::table('demo_orders', function (Blueprint $table) {
            $table->renameColumn('market_type', 'market');
            $table->renameColumn('type', 'side');
            $table->renameColumn('total', 'amount');
        });

        // Modify existing columns to match precision and types
        Schema::table('demo_orders', function (Blueprint $table) {
            $table->decimal('quantity', 28, 8)->change();
            $table->decimal('price', 28, 8)->nullable()->change();
            $table->string('market')->default('NGX')->change();
        });

        // Add missing columns (Handled conditionally for SQLite compatibility)
        Schema::table('demo_orders', function (Blueprint $table) {
            $table->string('currency')->default('NGN')->after('market');
            $table->string('company')->nullable()->after('currency');

            if (DB::getDriverName() === 'sqlite') {
                $table->string('type')->default('limit')->after('side');
            } else {
                $table->enum('type', ['market', 'limit'])->default('limit')->after('side');
            }

            $table->decimal('filled_quantity', 28, 8)->default(0)->after('quantity');
            $table->string('source')->nullable()->after('status');
            $table->decimal('units', 15, 6)->default(0)->after('company');
            $table->decimal('market_price', 15, 2)->default(0)->after('amount');

            if (DB::getDriverName() === 'sqlite') {
                $table->string('service_mode')->default('test')->after('market_price');
            } else {
                $table->enum('service_mode', ['live', 'test', 'dummy'])->default('test')->after('market_price');
            }

            $table->timestamp('matched_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->string('adapter')->nullable();
        });

        // Wipe existing fake orders
        DB::table('demo_orders')->truncate();

        //Update status (THE FIX )
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('demo_orders', function (Blueprint $table) {
                $table->string('status')->default('open')->change();
            });
        } else {
            DB::statement("ALTER TABLE demo_orders MODIFY COLUMN status ENUM('open', 'partially_filled', 'filled', 'canceled') DEFAULT 'open'");
        }
    }

    public function down(): void
    {
        // Reverse status change
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE demo_orders MODIFY COLUMN status ENUM('open', 'closed') DEFAULT 'closed'");
        }

        Schema::table('demo_orders', function (Blueprint $table) {
            $table->dropColumn([
                'company',
                'currency',
                'type',
                'filled_quantity',
                'source',
                'units',
                'market_price',
                'service_mode',
                'matched_at',
                'settled_at',
                'adapter'
            ]);

            // Revert modifications
            $table->integer('quantity')->change();
            $table->decimal('price', 15, 4)->nullable(false)->change();

            $table->renameColumn('market', 'market_type');
            $table->renameColumn('side', 'type');
            $table->renameColumn('amount', 'total');
        });
    }
};
