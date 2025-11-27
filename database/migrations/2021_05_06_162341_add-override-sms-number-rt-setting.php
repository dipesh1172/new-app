<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use App\Models\RuntimeSetting;

class AddOverrideSmsNumberRtSetting extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $r = new RuntimeSetting();
        $r->namespace = 'system';
        $r->name = 'override_sms_number';
        $r->value = '';
        $r->description = 'Force outgoing SMS to use the specified number if not empty';
        $r->save();
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
