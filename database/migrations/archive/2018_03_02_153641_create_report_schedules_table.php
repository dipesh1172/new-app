<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateReportSchedulesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('report_schedules', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('report_id');
			$table->string('name', 191);
			$table->integer('user_id');
			$table->enum('frequency', array('once','1min','5min','15min','30min','hourly','daily','weekly','quarterly','yearly'));
			$table->string('run_when', 191)->nullable();
			$table->boolean('enabled')->default(1);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('report_schedules');
	}

}
