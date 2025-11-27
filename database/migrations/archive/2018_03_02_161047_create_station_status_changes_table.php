<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateStationStatusChangesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('station_status_changes', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('station');
			$table->integer('reqestedBy');
			$table->integer('status');
			$table->boolean('handled')->default(0);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('station_status_changes');
	}

}
