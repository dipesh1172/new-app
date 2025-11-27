<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatsProductSurveyId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'stats_product',
            function (Blueprint $table) {
                $table->string('survey_id', 36)->after('hrtpv')->nullable();
                $table->string('lead_id', 36)->after('survey_id')->nullable();
            }
        );

        Schema::table(
            'daily_stats',
            function (Blueprint $table) {
                $table->double('survey_live_min')->default(0)->nullable();
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
        //
    }
}
