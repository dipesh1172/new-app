<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddHighVolumeRuntimeSetting extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('runtime_settings')->insert(['name' => 'high_volume', 'value' => 0, 'namespace' => 'system', 'created_at' => $now, 'updated_at' => $now]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // there is no down, only Zuul
    }
}
