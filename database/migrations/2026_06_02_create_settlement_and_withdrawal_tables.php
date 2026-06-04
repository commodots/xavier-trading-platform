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
        // Ensure settlement fields exist on trades table
        if (!Schema::hasColumn('trades', 'settlement_date')) {
            Schema::table('trades', function (Blueprint $table) {
                $table->timestamp('settlement_date')->nullable();
            });
        }

        if (!Schema::hasColumn('trades', 'is_settled')) {
            Schema::table('trades', function (Blueprint $table) {
                $table->boolean('is_settled')->default(false);
            });
        }

        // Create withdrawal requests table
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('currency'); // NGN, USD
            $table->string('bank_code')->nullable();
            $table->string('account_number');
            $table->string('account_name');
            $table->enum('status', ['pending', 'approved', 'rejected', 'completed', 'failed'])->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('status');
        });

        // Create withdrawal rules/limits table
        Schema::create('withdrawal_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('daily_limit_ngn')->default(500000); // Default 500k
            $table->integer('daily_limit_usd')->default(2500); // Default $2,500
            $table->integer('daily_withdrawn_ngn')->default(0);
            $table->integer('daily_withdrawn_usd')->default(0);
            $table->timestamp('last_reset_at')->nullable();
            $table->timestamp('cooldown_until')->nullable(); // For fraud protection
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_limits');
        Schema::dropIfExists('withdrawal_requests');
        
        if (Schema::hasColumn('trades', 'settlement_date')) {
            Schema::table('trades', function (Blueprint $table) {
                $table->dropColumn('settlement_date');
            });
        }

        if (Schema::hasColumn('trades', 'is_settled')) {
            Schema::table('trades', function (Blueprint $table) {
                $table->dropColumn('is_settled');
            });
        }
    }
};
