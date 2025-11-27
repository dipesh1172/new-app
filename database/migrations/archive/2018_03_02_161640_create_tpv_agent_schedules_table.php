<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTpvAgentSchedulesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tpv_agent_schedules', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('user_id');
			$table->integer('day_num');
			$table->integer('shift_start');
			$table->integer('shift_end');
			$table->integer('approved_by')->nullable();
			$table->dateTime('approved')->nullable();
			$table->integer('master_id')->nullable();
			$table->integer('current_status')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tpv_agent_schedules');
	}

}
