<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddScriptQuestionAnswerIndex extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'script_answers',
            function (Blueprint $table) {
                $table->index(['script_id', 'question_id'])->index('index_script_question');
                $table->index(['interaction_id'])->index('index_interaction_id');
                $table->index(['event_id'])->index('index_script_event_id');
            }
        );

        Schema::table(
            'script_questions',
            function (Blueprint $table) {
                $table->index(['script_id'])->index('index_q_script');
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
        //
    }
}
