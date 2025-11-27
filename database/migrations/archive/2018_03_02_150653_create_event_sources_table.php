<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreateEventSourcesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('event_sources', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			$table->softDeletes()->index('idx_event_sources_deleted_at');
			$table->string('source', 16)->nullable();
		});

		$dt = new DateTime;

	    DB::table('event_sources')->insert(
	    	array(
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'source' => 'Live'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'source' => 'IVR'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'source' => 'Unknown'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'source' => 'Closed'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'source' => 'Inactive'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'source' => 'Auto'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'source' => 'Cancel'
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
		Schema::drop('event_sources');
	}

}
