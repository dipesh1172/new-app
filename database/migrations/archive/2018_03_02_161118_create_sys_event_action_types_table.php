<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreateSysEventActionTypesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('sys_event_action_types', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->string('name', 191);
			$table->text('description', 65535)->nullable();
			$table->text('variables', 65535)->nullable();
		});

		$dt = new DateTime;

	    DB::table('sys_event_action_types')->insert(
	    	array(
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'name' => 'Email',
		            'description' => 'Emails content to a user',
		            'variables' => 'emailto,subject,content'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'name' => 'Chat Message',
		            'description' => 'Sends a message to an individual',
		            'variables' => 'sendto,content'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'name' => 'Chat Broadcast',
		            'description' => 'Send a broadcast message',
		            'variables' => 'content'
		        )
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
		Schema::drop('sys_event_action_types');
	}

}
