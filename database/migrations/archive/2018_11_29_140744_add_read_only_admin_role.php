<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AddReadOnlyAdminRole extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('user_roles')->insert(
            [
                [
                    'id' => 4,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'name' => 'Administrator (Read Only)',
                    'brand_id' => '04B0F894-172C-470F-813B-4F58DBD35BAE'
                ],
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
        DB::table('user_roles')->where('id', 4)->delete();
    }
}
