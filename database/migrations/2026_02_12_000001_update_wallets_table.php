<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('wallets', function (Blueprint $table) {
            $table->decimal('ngn_cleared', 18, 2)->default(0)->after('currency');
            $table->decimal('ngn_uncleared', 18, 2)->default(0)->after('ngn_cleared');
            $table->decimal('usd_cleared', 18, 2)->default(0)->after('ngn_uncleared');
            $table->decimal('usd_uncleared', 18, 2)->default(0)->after('usd_cleared');
        });

        // Migrate existing values from generic cleared/uncleared into currency-specific columns
        if (Schema::hasColumn('wallets', 'cleared_balance') && Schema::hasColumn('wallets', 'uncleared_balance')) {
            $wallets = \DB::table('wallets')->get();

            foreach ($wallets as $w) {
                if ($w->currency === 'NGN') {
                    \DB::table('wallets')->where('id', $w->id)->update([
                        'ngn_cleared' => $w->cleared_balance ?? 0,
                        'ngn_uncleared' => $w->uncleared_balance ?? 0,
                    ]);
                }

                if ($w->currency === 'USD') {
                    \DB::table('wallets')->where('id', $w->id)->update([
                        'usd_cleared' => $w->cleared_balance ?? 0,
                        'usd_uncleared' => $w->uncleared_balance ?? 0,
                    ]);
                }
            }

            // Drop legacy columns
            Schema::table('wallets', function (Blueprint $table) {

                if (Schema::hasColumn('wallets', 'cleared_balance')) {
                    $table->dropColumn('cleared_balance');
                }
                if (Schema::hasColumn('wallets', 'uncleared_balance')) {
                    $table->dropColumn('uncleared_balance');
                }
            });
        }
    }

    public function down()
    {
        // Recreate generic columns and move values back
        Schema::table('wallets', function (Blueprint $table) {

            if (! Schema::hasColumn('wallets', 'cleared_balance')) {
                $table->decimal('cleared_balance', 20, 6)->default(0)->after('balance');
            }
            if (! Schema::hasColumn('wallets', 'uncleared_balance')) {
                $table->decimal('uncleared_balance', 20, 6)->default(0)->after('cleared_balance');
            }
        });

        $wallets = \DB::table('wallets')->get();
        foreach ($wallets as $w) {
            $update = [];
            if ($w->currency === 'NGN') {
                $update['cleared_balance'] = $w->ngn_cleared ?? 0;
                $update['uncleared_balance'] = $w->ngn_uncleared ?? 0;
            }
            if ($w->currency === 'USD') {
                $update['cleared_balance'] = $w->usd_cleared ?? 0;
                $update['uncleared_balance'] = $w->usd_uncleared ?? 0;
            }

            if (! empty($update)) {
                \DB::table('wallets')->where('id', $w->id)->update($update);
            }
        }

        Schema::table('wallets', function (Blueprint $table) {
            if (Schema::hasColumn('wallets', 'ngn_cleared')) {
                $table->dropColumn(['ngn_cleared', 'ngn_uncleared', 'usd_cleared', 'usd_uncleared']);
            }
        });
    }
};
