<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddScriptQuestionsLoopTypes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'script_questions_loop_types', function (Blueprint $table) {
                $table->increments('id')->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('loop_type', 64);
            }
        );

        Schema::table(
            'script_questions', function (Blueprint $table) {
                $table->integer('loop_type')
                    ->nullable()
                    ->default(null)
                    ->after('loop');
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
        Schema::dropIfExists('script_questions_loop_types');

        Schema::table(
            'script_questions', function (Blueprint $table) {
                $table->dropColumn('loop_type');
            }
        );
    }
}
