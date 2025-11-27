<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateMotionSkillMapsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('motion_skill_maps', function (Blueprint $table) {
            $table->string('id', 36)->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_id', 36)->nullable();
            $table->string('dnis_id', 36)->nullable();
            $table->integer('language_id')->nullable();
            $table->string('motion_skills_id', 36)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('motion_skill_maps');
    }
}
