<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class AddCloseOverrideRuntimeSetting extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::table('runtime_settings')->insert([
            [
                'created_at' => now(),
                'updated_at' => now(),
                'namespace' => 'system',
                'name' => 'close_at_override',
                'value' => '',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // There is no down, only Zuul
    }
}
