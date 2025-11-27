<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTpvCallInformationTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tpv_call_information', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->softDeletes();
			$table->integer('user_id');
			$table->string('session_id', 191);
			$table->integer('call_length')->nullable();
			$table->integer('call_handle_time')->nullable();
			$table->string('call_started', 191)->nullable();
			$table->integer('call_wrapup_time')->nullable();
			$table->string('timestamp', 191)->nullable();
			$table->string('campaign', 191);
			$table->string('ani', 191);
			$table->string('dnis', 191);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tpv_call_information');
	}

}
