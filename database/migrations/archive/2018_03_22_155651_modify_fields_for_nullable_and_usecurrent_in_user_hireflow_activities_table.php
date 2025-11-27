<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyFieldsForNullableAndUsecurrentInUserHireflowActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_hireflow_activities', function (Blueprint $table) {
            $table->string('user_id', 36)->nullable()->change();
            $table->string('hireflow_id', 36)->nullable()->change();
            $table->string('hireflow_item_id', 36)->nullable()->change();
            $table->string('pdf_path', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_hireflow_activities', function (Blueprint $table) {
            $table->string('user_id', 36)->nullable(false)->change();
            $table->string('hireflow_id', 36)->nullable(false)->change();
            $table->string('hireflow_item_id', 36)->nullable(false)->change();
            $table->string('pdf_path', 255)->nullable(false)->change();
        });
    }
}
