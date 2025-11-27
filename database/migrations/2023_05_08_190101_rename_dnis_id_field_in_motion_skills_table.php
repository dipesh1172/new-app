<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameDnisIdFieldInMotionSkillsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('motion_skills', function (Blueprint $table) {
            $table->renameColumn('dnis_id', 'dnis');
            $table->index('name');
            $table->index('language_id');
            $table->index('dnis');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('motion_skills', function (Blueprint $table) {
            $table->renameColumn('dnis', 'dnis_id');
            $table->dropIndex('name');
            $table->dropIndex('language_id');
            $table->dropIndex('dnis');
        });
    }
}
