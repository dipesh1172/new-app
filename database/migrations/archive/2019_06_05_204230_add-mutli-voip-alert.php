<?php

use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AddMutliVoipAlert extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('client_alerts')->insert(
            [
                'created_at' => $now,
                'updated_at' => $now,
                'title' => 'VOIP Phone has been used by Sales Agent for multple Good Sales',
                'channels' => 'DTD,TM',
                'description' => 'Sends an alert if the sales agent has had more than the threshold of good sales that were from a VOIP number',
                'threshold' => 2,
                'function' => 'checkHasMultipleVoipUsagesToday',
                'category_id' => 4,
                'can_stop_call' => 0,
                'has_threshold' => 1,
                'client_alert_type_id' => 2,
                'sort' => 15,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        $now = Carbon::now();
        DB::table('client_alerts')
            ->where('function', 'checkHasMultipleVoipUsagesToday')
            ->update(
                [
                    'deleted_at' => $now,
                ]
            );
    }
}
