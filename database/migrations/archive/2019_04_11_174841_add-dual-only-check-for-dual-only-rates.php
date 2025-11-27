<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class AddDualOnlyCheckForDualOnlyRates extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $now = Carbon::now();
        $existing = DB::table('client_alerts')->where('title', 'Dual Only Rate In Use')->first();
        if ($existing === null) {
            DB::table('client_alerts')->insert(
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'title' => 'Dual Only Rate In Use',
                    'channels' => 'DTD,Retail,TM',
                    'description' => 'Sends an alert if a rate designated as Dual Fuel only is chosen for a single fuel enrollment.',
                    'function' => 'checkDualFuelOnlyRatesInUseWithSingleFuelEnrollment',
                    'threshold' => 0,
                    'category_id' => 2,
                    'can_stop_call' => 0,
                    'has_threshold' => 0,
                    'client_alert_type_id' => 3,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // There is no down, only Zuul.
    }
}
