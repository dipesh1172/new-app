<?php

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;

class AddAutoFlagReason extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $c = DB::table('event_flag_reasons')->where('id', '00000000000000000000000000000002')->count();
        if ($c == 0) {
            DB::table('event_flag_reasons')->insert(
                [
                    [
                        'id' => '00000000000000000000000000000002',
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now(),
                        'fraud_indicator' => 0,
                        'description' => 'Automatically Flagged Based on Disposition',
                        'show_to_agents' => 0,
                    ],
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('event_flag_reasons')->where('id', '00000000000000000000000000000002')->delete();
    }
}
