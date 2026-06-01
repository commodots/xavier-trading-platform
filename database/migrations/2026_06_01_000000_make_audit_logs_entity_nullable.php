<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'entity')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->string('entity')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'entity')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->string('entity')->nullable(false)->change();
            });
        }
    }
};
