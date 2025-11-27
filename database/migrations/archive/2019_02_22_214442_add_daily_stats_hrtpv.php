<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDailyStatsHrtpv extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'daily_stats',
            function (Blueprint $table) {
                $table->double('hrtpv_live_min')->default(0);
                $table->integer('hrtpv_records')->nullable();
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
            'daily_stats',
            function (Blueprint $table) {
                $table->dropColumn('hrtpv_live_min');
                $table->dropColumn('hrtpv_records');
            }
        );
    }
}
