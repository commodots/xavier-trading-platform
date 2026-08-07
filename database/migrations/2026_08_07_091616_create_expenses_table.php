<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('expense_no')->unique();
            $table->foreignId('expense_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 18, 2);
            $table->date('expense_date');
            $table->enum('payment_method', ['cash', 'bank_transfer', 'card', 'wallet', 'other'])->default('bank_transfer');
            $table->string('reference')->nullable();
            $table->string('invoice_number')->nullable();
            $table->text('description');
            $table->enum('status', ['draft', 'approved', 'paid', 'cancelled'])->default('draft');
            $table->timestamps();

            $table->index('expense_date');
            $table->index('status');
            $table->index('expense_category_id');
            $table->index('vendor_id');
            $table->index('department_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
