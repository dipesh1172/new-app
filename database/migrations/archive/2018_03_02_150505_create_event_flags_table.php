<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateEventFlagsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('event_flags', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			$table->softDeletes()->index('idx_event_flags_deleted_at');
			$table->string('event_id', 36)->nullable();
			$table->string('flagged_by_id', 36)->nullable();
			$table->string('flag_reason_id', 36)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('event_flags');
	}

}
