<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDailyQuestionSubmissions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('daily_question_submissions', function (Blueprint $table) {
            $table->string('id', 36)->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_id', 36);
            $table->string('script_id', 36);
            $table->string('brand_user_id', 36);
            $table->tinyInteger('result')->default(0);
            $table->text('response');
        });
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
