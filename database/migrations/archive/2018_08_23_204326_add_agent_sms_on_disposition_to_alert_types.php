<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AddAgentSmsOnDispositionToAlertTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('client_alerts')->insert(
            [
                'created_at' => $now,
                'updated_at' => $now,
                'title' => 'Agent SMS with Disposition', 
                'channels' => 'DTD', 
                'description' => 'Sends an SMS to the Agent after a event is disposition and includes no sale info if needed', 
                'threshold' => 0, 
                'function' => 'agentGetSMSUpdate', 
                'category_id' => 4,
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
        DB::table('client_alerts')->where('function', 'agentGetSMSUpdate')->delete();
    }
}
