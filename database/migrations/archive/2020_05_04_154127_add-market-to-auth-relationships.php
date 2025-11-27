<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMarketToAuthRelationships extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('auth_relationships', function (Blueprint $table) {
            $table->integer('market_id')->default(1);
        });
        $sc = DB::table('default_sc_company_positions')->select('title')->get();
        $insert = [];
        foreach ($sc as $position) {
            $insert[] = [
                'created_at' => now(),
                'updated_at' => now(),
                'relationship' => $position->title,
                'market_id' => 2,
            ];
        }
        DB::table('auth_relationships')->insert($insert);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('auth_relationships')->where('market_id', '<>', 1)->delete();
        Schema::table('auth_relationships', function (Blueprint $table) {
            $table->dropColumn('market_id');
        });
    }
}
