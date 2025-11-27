<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCustomEztpvScriptType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('script_types')->insert([
            [
                'id' => 6,
                'created_at' => now(),
                'updated_at' => now(),
                'script_type' => 'Custom EzTPV',
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::table('script_types')->where('id', 6)->delete();
    }
}
