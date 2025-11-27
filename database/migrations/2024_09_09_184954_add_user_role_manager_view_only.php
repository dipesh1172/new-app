<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class AddUserRoleManagerViewOnly extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('user_roles')->insert(
            [
                'created_at' => $now,
                'updated_at' => $now,
                'name' => 'Manager (Read Only)',
                'brand_id' => '04B0F894-172C-470F-813B-4F58DBD35BAE'
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
        DB::table('user_roles')->where('name', 'Manager (Read Only)')->delete();
    }
}
