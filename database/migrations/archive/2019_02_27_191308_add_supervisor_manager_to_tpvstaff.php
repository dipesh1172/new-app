<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSupervisorManagerToTpvstaff extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('tpv_staff', function (Blueprint $table) {
            $table->string('supervisor_id', 36)->default(-1);
            $table->string('manager_id', 36)->default(-1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tpv_staff', function (Blueprint $table) {
            $table->dropColumn('supervisor_id');
            $table->dropColumn('manager_id');
        });
    }
}
