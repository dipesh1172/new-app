<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTotalEztpvsColumnToDailyStatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('daily_stats', function (Blueprint $table) {
            $table->integer('total_eztpvs')->after('dnis_local');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('daily_stats', function (Blueprint $table) {
            $table->removeColumn('total_eztpvs');
        });
    }
}
