<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateBillingFieldsOnStatsProductTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stats_product', function (Blueprint $table) {
            $table->string('bill_first_name', 255)->nullable()->change();
            $table->string('bill_middle_name', 255)->nullable()->change();
            $table->string('bill_last_name', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stats_product', function (Blueprint $table) {
            $table->string('bill_first_name', 36)->nullable()->change();
            $table->string('bill_middle_name', 36)->nullable()->change();
            $table->string('bill_last_name', 36)->nullable()->change();
        });
    }
}
