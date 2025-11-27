<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateStationStatusHistoriesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('station_status_histories', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('station');
			$table->integer('status');
			$table->integer('user');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('station_status_histories');
	}

}
