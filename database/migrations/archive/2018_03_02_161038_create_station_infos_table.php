<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateStationInfosTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('station_infos', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('station');
			$table->string('key', 191);
			$table->text('value', 65535)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('station_infos');
	}

}
