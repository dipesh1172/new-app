<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class FixTypeDualFuelOnlyCheck extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::table('client_alerts')->where('function', 'checkDualFuelOnlyRatesInUseWithSingleFuelEnrollment')->update(['client_alert_type_id' => 1]);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // there is no down, only Zuul
    }
}
