<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateScriptQuestionsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('script_questions', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->integer('loop')->nullable()->default(0);
			$table->integer('section_id')->nullable();
			$table->integer('subsection_id')->nullable();
			$table->integer('question_id')->nullable();
			$table->string('script_id', 36)->nullable();
			$table->text('question', 65535)->nullable();
			$table->string('state', 128)->nullable()->default('');
			$table->text('resolution', 65535)->nullable();
			$table->text('notes', 65535)->nullable();
			$table->text('exit_reason', 65535)->nullable();
			$table->integer('allow_good_sale')->default(0);
			$table->integer('status')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('script_questions');
	}

}
