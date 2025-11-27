<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSentToIvrInteractionType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('interaction_types')->insert([
            'id' => 22,
            'created_at' => now(),
            'updated_at' => now(),
            'name' => 'link_tracking',
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('interaction_types')->where('name', 'link_tracking')->delete();
    }
}
