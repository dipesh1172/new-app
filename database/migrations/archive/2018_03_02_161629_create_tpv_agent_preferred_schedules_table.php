<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTpvAgentPreferredSchedulesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tpv_agent_preferred_schedules', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('user_id');
			$table->integer('day_num');
			$table->integer('shift_start');
			$table->integer('shift_end');
			$table->string('comment', 191)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tpv_agent_preferred_schedules');
	}

}
