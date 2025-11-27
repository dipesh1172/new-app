<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateStationsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('stations', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->string('station_id', 191);
			$table->integer('current_user')->nullable();
			$table->string('status', 191)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('stations');
	}

}
