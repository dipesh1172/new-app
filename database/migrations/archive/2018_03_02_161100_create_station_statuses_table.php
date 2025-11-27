<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateStationStatusesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('station_statuses', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('status_code');
			$table->string('description', 191);
			$table->string('map_from', 191)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('station_statuses');
	}

}
