<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreateTpvStaffPermissionsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tpv_staff_permissions', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			$table->softDeletes();
			$table->string('short_name');
			$table->string('friendly_name');
			$table->text('description', 65535);
		});

		$dt = new DateTime;

	    DB::table('tpv_staff_permissions')->insert(
	    	array(
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'short_name' => 'users.show.all',
		            'friendly_name' => 'Show all users',
		            'description' => 'Show all users regardless of department.'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'short_name' => 'users.edit.all',
		            'friendly_name' => 'Edit all users',
		            'description' => 'Can edit any user regardless of department.'
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
		Schema::drop('tpv_staff_permissions');
	}

}
