<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\Report;

class AddDailyDeactivationReportToReports extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $r = new Report();
        $r->name = 'Daily Deactivation';
        $r->description = 'This report shows what agents were automatically deactivated for not having sales in the configured time range.';
        $r->icon = 'fa-map-o';
        $r->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // There is no down, only Zuul!
    }
}
