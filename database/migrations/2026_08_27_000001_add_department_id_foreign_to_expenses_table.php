<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The department_id column already exists on the expenses table, but it was
     * created as a plain unsignedBigInteger without a foreign key constraint
     * because the departments table is created later in the migration order.
     * This ties department_id to departments.id (nullOnDelete) so that expense
     * reporting by department stays referentially consistent.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('expenses', 'department_id')) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->after('vendor_id');
            });
        }

        // Idempotent: only add the constraint when it does not already exist.
        $hasForeignKey = collect(Schema::getForeignKeys('expenses'))
            ->pluck('name')
            ->contains(fn ($name) => str_contains((string) $name, 'department_id'));

        if (! $hasForeignKey) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->foreign('department_id')
                    ->references('id')
                    ->on('departments')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $hasForeignKey = collect(Schema::getForeignKeys('expenses'))
            ->pluck('name')
            ->contains(fn ($name) => str_contains((string) $name, 'department_id'));

        if ($hasForeignKey) {
            Schema::table('expenses', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
            });
        }
    }
};
