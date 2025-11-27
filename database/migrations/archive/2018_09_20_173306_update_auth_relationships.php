<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class UpdateAuthRelationships extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $relations = DB::table('auth_relationships')->select('id', 'relationship')->get();
        $relations->each(
            function ($item, $key) {
                DB::table('event_product')->where('auth_relationship', $item->relationship)->update(['auth_relationship' => $item->id]);
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
