<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csl_customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('mobile_phone_no');
            $table->string('gender_cd');
            $table->string('address_street');
            $table->string('address_city');
            $table->string('address_country_cd', 20);
            $table->string('bank_cd');
            $table->string('bank_account_name');
            $table->text('bank_account_no');
            $table->string('bank_branch_name');
            $table->text('bank_address_details');
            $table->text('bank_bvn_no');
            $table->string('customer_id')->nullable()->unique();
            $table->string('status')->default('not_submitted');
            $table->text('last_error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csl_customer_profiles');
    }
};
