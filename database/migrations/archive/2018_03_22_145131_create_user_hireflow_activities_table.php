<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateUserHireflowActivitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_hireflow_activities', function (Blueprint $table) {
            $table->string('id', 36);
            $table->timestamps();
            $table->softDeletes();
            $table->string('user_id', 36);
            $table->string('hireflow_id', 36);
            $table->string('hireflow_item_id', 36);
            $table->string('pdf_path', 255);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_hireflow_activities');
    }
}
