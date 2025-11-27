<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class AddAgentConfirmationEventSource extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $now = Carbon::now();
        $exists = DB::table('event_sources')->where('source', 'Agent Confirmation')->count();
        if ($exists === 0) {
            DB::table('event_sources')->insert(['created_at' => $now, 'updated_at' => $now, 'source' => 'Agent Confirmation']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::table('event_sources')->where('source', 'Agent Confirmation')->delete();
    }
}
