<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUtilityMonthlyFees extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('utility_supported_fuels', function (Blueprint $table) {
            $table->double('utility_monthly_fee')->nullable();
            $table->double('utility_rate_addendum')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('utility_supported_fuels', function (Blueprint $table) {
            $table->dropColumn(['utility_monthly_fee', 'utility_rate_addendum']);
        });
    }
}
