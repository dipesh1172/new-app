<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateUserRoleManagerViewOnly extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_roles', function (Blueprint $table) {
            DB::table('user_roles')->where('id', 5)
            ->update(
                [
                    'name' => 'Admin (Events-Only)'
                ]
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_roles', function (Blueprint $table) {
            DB::table('user_roles')->where('id', 5)
            ->update(
                [
                    'name' => 'Manager (Read Only)'
                ]
            );
        });
    }
}
