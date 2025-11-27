<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOrderToHireflowItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('hireflow_items', function (Blueprint $table) {
            $table->integer('order')->nullable()->after('hireflow_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('hireflow_items', function (Blueprint $table) {
            $table->dropColumn('order');
        });
    }
}
