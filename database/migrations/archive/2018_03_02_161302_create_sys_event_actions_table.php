<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateSysEventActionsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('sys_event_actions', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('action_type');
			$table->integer('event_type');
			$table->integer('template_id');
			$table->integer('permission_id')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('sys_event_actions');
	}

}
