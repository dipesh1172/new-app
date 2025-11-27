<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateReportFunctionsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('report_functions', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->string('name', 191);
			$table->string('description', 191);
			$table->string('callable', 191);
			$table->string('parameters', 191);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('report_functions');
	}

}
