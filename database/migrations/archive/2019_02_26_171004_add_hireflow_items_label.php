<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHireflowItemsLabel extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'hireflow_items',
            function (Blueprint $table) {
                $table->string('label', 128)->after('item_id')->nullable();
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
        Schema::table(
            'hireflow_items',
            function (Blueprint $table) {
                $table->dropColumn('label');
            }
        );
    }
}
