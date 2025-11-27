<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDailyStatsEztpvChannels extends Migration
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
                $table->integer('total_dtd_eztpvs')
                    ->after('total_eztpvs')->nullable()->default(0);
                $table->integer('total_retail_eztpvs')
                    ->after('total_dtd_eztpvs')->nullable()->default(0);
                $table->integer('total_tm_eztpvs')
                    ->after('total_retail_eztpvs')->nullable()->default(0);
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
                $table->dropColumn('total_dtd_eztpvs');
                $table->dropColumn('total_retail_eztpvs');
                $table->dropColumn('total_tm_eztpvs');
            }
        );
    }
}
