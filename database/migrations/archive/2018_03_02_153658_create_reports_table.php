<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateReportsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('reports', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('report_type');
			$table->text('report_parameters', 65535)->nullable();
			$table->binary('report_data', 65535)->nullable();
			$table->integer('user_id')->nullable();
			$table->string('mail_to', 191)->nullable();
			$table->string('data_mime_type', 191)->default('text/plain');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('reports');
	}

}
