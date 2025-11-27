<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddExpiredLinkAccessToInteractionTypesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if(!DB::table('interaction_types')->where('id', 31)->exists()){
            DB::table('interaction_types')->insert([
                'id' => 31,
                'created_at' => now(),
                'updated_at' => now(),
                'name' => 'expired_link_access'
            ]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('interaction_types')
            ->where('id', 31)
            ->delete();
    }
}
