<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTpvCallProjectedTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tpv_call_projected', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('forecast');
			$table->integer('avg_speed_to_answer');
			$table->integer('agents_required');
			$table->integer('avg_handle_time');
			$table->integer('interval')->nullable();
			$table->integer('lang_id')->nullable();
			$table->integer('day_num')->nullable();
			$table->integer('calls_per_period')->nullable();
			$table->float('calls_per_hour', 8, 1)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tpv_call_projected');
	}

}
