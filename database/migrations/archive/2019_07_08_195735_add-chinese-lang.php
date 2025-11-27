<?php

use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AddChineseLang extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('languages')->insert(
            [
                'created_at' => $now,
                'updated_at' => $now,
                'language' => 'Chinese',
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // there is no down, only Zuul!
    }
}
