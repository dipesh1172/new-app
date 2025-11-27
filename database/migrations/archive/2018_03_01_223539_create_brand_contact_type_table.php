<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreateBrandContactTypeTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('brand_contact_type', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			$table->softDeletes();
			$table->string('contact_type', 64)->nullable();
		});

		$dt = new DateTime;

	    DB::table('brand_contact_type')->insert(
	    	array(
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'contact_type' => 'Project Manager',
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'contact_type' => 'Technical Contact',
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'contact_type' => 'Sales Contact',
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'contact_type' => 'Invoice Contact',
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
		Schema::drop('brand_contact_type');
	}

}
