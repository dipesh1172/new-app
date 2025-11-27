<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreateRuntimeSettingsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('runtime_settings', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('modified_by');
			$table->string('name', 191);
			$table->string('value', 191);
			$table->string('namespace', 191)->default('system');
		});

		$dt = new DateTime;

	    DB::table('runtime_settings')->insert(
	    	array(
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'modified_by' => 1,
		            'name' => 'desired_occupancy',
		            'value' => 0.75,
		            'namespace' => 'scheduling'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'modified_by' => 1,
		            'name' => 'forecast_weeks_history',
		            'value' => 2,
		            'namespace' => 'scheduling'
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
		Schema::drop('runtime_settings');
	}

}
