<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\EventFlagReason;

class AddIvrScriptEventFlag extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $efr = new EventFlagReason();
        $efr->fraud_indicator = false;
        $efr->show_to_agents = false;
        $efr->description = 'IVR Voice Response lacks confidence';
        $efr->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $efr = EventFlagReason::where('description', 'IVR Voice Response lacks confidence')->first();
        if ($efr) {
            $efr->forceDelete();
        }
    }
}
