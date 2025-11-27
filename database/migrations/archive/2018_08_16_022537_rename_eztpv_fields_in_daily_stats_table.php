<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameEztpvFieldsInDailyStatsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('daily_stats', function (Blueprint $table) {
            $table->renameColumn('eztpv_basic', 'eztpv_contract');
            $table->renameColumn('eztpv_plus', 'eztpv_photo');
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
            $table->renameColumn('eztpv_contract', 'eztpv_basic');
            $table->renameColumn('eztpv_photo', 'eztpv_plus');
        });
    }
}
