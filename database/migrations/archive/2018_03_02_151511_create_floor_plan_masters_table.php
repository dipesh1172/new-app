<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateFloorPlanMastersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('floor_plan_masters', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->string('location', 3);
			$table->string('location_name', 191);
			$table->integer('rows');
			$table->integer('max_per_row');
			$table->string('timezone', 191)->default('America/Chicago');
			$table->integer('break_length')->default(15);
			$table->integer('lunch_length')->default(30);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('floor_plan_masters');
	}

}
