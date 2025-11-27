<?php

use App\Models\RuntimeSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddsSettingsForPayrollCommunication extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $newones = ['tulsa_adp_enabled', 'las_vegas_adp_enabled', 'tahlequah_adp_enabled', 'at_home_adp_enabled'];

        foreach ($newones as $newone) {
            $rs = new RuntimeSetting();
            $rs->name = $newone;
            $rs->value = 0;
            $rs->namespace = 'payroll';
            $rs->modified_by = 1;
            $rs->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        RuntimeSetting::where('namespace', 'payroll')->delete();
    }
}
