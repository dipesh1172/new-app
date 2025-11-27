<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ExtendScriptAnswerQuestionId extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('script_answers', function (Blueprint $table) {
            $table->string('question_id', 100)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('script_answers', function (Blueprint $table) {
            // there is no down, only Zuul
        });
    }
}
