<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreateAlertEventActionTypesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('alert_event_action_types', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			$table->string('name');
			$table->text('description', 65535)->nullable();
			$table->text('variables', 65535)->nullable();
		});

	    DB::table('alert_event_action_types')->insert(
	        array(
	            'name' => 'Short Message Service',
	            'description' => 'Send a text message',
	            'variables' => 'name,msg'
	        )
	    );
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('alert_event_action_types');
	}

}
