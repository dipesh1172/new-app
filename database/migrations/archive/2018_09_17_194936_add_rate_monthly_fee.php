<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRateMonthlyFee extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'rates',
            function (Blueprint $table) {
                $table->float('rate_monthly_fee')
                    ->nullable()->after('rate_amount');
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(
            'rates',
            function (Blueprint $table) {
                $table->removeColumn('rate_monthly_fee');
            }
        );
    }
}
