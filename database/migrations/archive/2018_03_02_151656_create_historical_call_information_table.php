<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateHistoricalCallInformationTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('historical_call_information', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('week_num');
			$table->integer('day_num');
			$table->integer('interval');
			$table->integer('calls');
			$table->integer('avg_call_time');
			$table->integer('avg_speed_of_answer');
			$table->integer('year')->default(0);
			$table->integer('lang_id')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('historical_call_information');
	}

}
