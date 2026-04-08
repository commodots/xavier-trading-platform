<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ensure the main watchlist table has the initial price field to track "Added Price"
        Schema::table('watchlists', function (Blueprint $table) {
            if (!Schema::hasColumn('watchlists', 'added_price')) {
                $table->decimal('added_price', 20, 8)->nullable()->after('price')->comment('Price at the time of adding to watchlist');
            }
        });

        // Create the price history table for tracking changes over time
        Schema::create('watchlist_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('watchlist_id')->constrained('watchlists')->onDelete('cascade');
            $table->decimal('price', 20, 8);
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_price_history');
        Schema::table('watchlists', function (Blueprint $table) {
            $table->dropColumn('added_price');
        });
    }
};
