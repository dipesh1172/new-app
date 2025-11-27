<?php

use App\Models\EventFlagReason;
use Illuminate\Database\Migrations\Migration;

class AddsSaleAgentInterruptedToEventFlagReasons extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $efr = new EventFlagReason();
        $efr->fraud_indicator = true;
        $efr->description = 'Sales Rep Interrupted';
        $efr->show_to_agents = true;
        $efr->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        EventFlagReason::where('description', 'Sales Rep Interrupted')->delete();
    }
}
