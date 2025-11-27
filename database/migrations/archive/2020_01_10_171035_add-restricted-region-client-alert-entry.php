<?php

use App\Models\ClientAlert;
use Illuminate\Database\Migrations\Migration;

class AddRestrictedRegionClientAlertEntry extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $ca = new ClientAlert();
        $ca->title = 'Restricted Region';
        $ca->channels = 'DTD,Retail,TM';
        $ca->description = 'Triggers when a restricted region selection is attempted';
        $ca->threshold = 0;
        $ca->function = 'restrictedRegion';
        $ca->category_id = 5;
        $ca->can_stop_call = false;
        $ca->has_threshold = false;
        $ca->client_alert_type_id = 1;
        $ca->sort = 16;

        $ca->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        ClientAlert::where('function', 'restrictedRegion')->delete();
    }
}
