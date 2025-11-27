<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateFloorPlansTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('floor_plans', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('row');
			$table->integer('col');
			$table->integer('station_id')->nullable();
			$table->integer('master')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('floor_plans');
	}

}
