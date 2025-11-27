<?php

use App\Models\TpvStaffRole;
use Illuminate\Database\Migrations\Migration;

class AddQaSupportRole extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tsp = new TpvStaffRole();
        $tsp->dept_id = 2;
        $tsp->name = "QA Support";
        $tsp->description = "QA Support";
        $tsp->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        TpvStaffRole::where('name', "QA Support")->delete();
    }
}
