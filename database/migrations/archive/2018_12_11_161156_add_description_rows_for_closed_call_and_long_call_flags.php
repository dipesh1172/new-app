<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDescriptionRowsForClosedCallAndLongCallFlags extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('event_flag_reasons')->insert(
            [
                [
                    'id' => '5dcdd6fb-faa2-4f3a-8e87-cc98db16b8b0',
                    'created_at' => '2018-12-11 08:51:39',
                    'updated_at' => '2018-12-11 08:51:39',
                    'description' => 'Closed Call',
                    'show_to_agents' => '0'
                ],
                [
                    'id' => '0afb2c0a-ffd1-4488-a258-eb628679e228',
                    'created_at' => '2018-12-11 08:55:39',
                    'updated_at' => '2018-12-11 08:55:39',
                    'description' => 'Call Time unusually high',
                    'show_to_agents' => '0'
                ],
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('event_flag_reasons')->where('id', '5dcdd6fb-faa2-4f3a-8e87-cc98db16b8b0')->delete();
        DB::table('event_flag_reasons')->where('id', '0afb2c0a-ffd1-4488-a258-eb628679e228')->delete();

    }
}
