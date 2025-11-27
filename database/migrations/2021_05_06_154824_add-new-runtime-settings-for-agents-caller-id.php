<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\RuntimeSetting;

class AddNewRuntimeSettingsForAgentsCallerId extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $r = new RuntimeSetting();
        $r->namespace = 'agents';
        $r->name = 'use_default_number';
        $r->value = '0';
        $r->description = 'Force outgoing calls to use the defined "default number"';
        $r->save();

        $r = new RuntimeSetting();
        $r->namespace = 'agents';
        $r->name = 'override_outgoing_number';
        $r->value = '0';
        $r->description = 'Force outgoing calls to use the specified number in agents::outgoing_number, this number MUST be valid for sending calls.';
        $r->save();

        $r = new RuntimeSetting();
        $r->namespace = 'agents';
        $r->name = 'outgoing_number';
        $r->value = '';
        $r->description = 'Use this number when making outgoing calls in Agents, must be valid for sending calls.';
        $r->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // There is no down, only Zuul
    }
}
