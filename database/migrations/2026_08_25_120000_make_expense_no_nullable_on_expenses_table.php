<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The expense number is assigned after creation using the database ID
     * (EXP-YYYYMM-000001). The column must be nullable so the record can be
     * created first, then the number derived from its unique ID.
     *
     * The original create_expenses_table migration already defines a UNIQUE
     * index on expense_no (`expenses_expense_no_unique`). Re-adding it here
     * crashes with MySQL error 1061 ("Duplicate key name") on databases where
     * the index exists — restored dumps or partially-applied runs — so this
     * migration is made idempotent: it only adds the unique index when it is
     * genuinely absent, and otherwise only relaxes the column's nullability.
     */
    public function up(): void
    {
        $hasUniqueIndex = collect(Schema::getIndexes('expenses'))
            ->contains(fn ($index) => str_contains((string) ($index['name'] ?? ''), 'expense_no'));

        if (! $hasUniqueIndex) {
            // Rare legacy shape: neither the index nor perhaps nullability.
            // One pass adds the unique index alongside the column change.
            Schema::table('expenses', function (Blueprint $table) {
                $table->string('expense_no')->nullable()->unique()->change();
            });

            return;
        }

        // Index already present: leave it alone. Only ensure the column is
        // nullable — MySQL's MODIFY keeps existing indexes untouched.
        $column = collect(Schema::getColumns('expenses'))->firstWhere('name', 'expense_no');

        if ($column && ($column['nullable'] ?? false)) {
            return; // Nothing left to do.
        }

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('expense_no')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('expense_no')->nullable(false)->change();
        });
    }
};