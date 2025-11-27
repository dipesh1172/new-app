<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CreateVoiceImprintInteractionType extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $now = Carbon::now();
        $check = DB::table('interaction_types')->where('name', 'voice_imprint')->first();
        if ($check == null) {
            DB::table('interaction_types')->insert(
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                    'name' => 'voice_imprint',
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::table('interaction_types')->where('name', 'voice_imprint')->delete();
    }
}
