<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateUtilityAccountTypesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('utility_account_types', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			$table->softDeletes();
			$table->string('account_type', 64)->nullable();
		});

		$dt = new DateTime;

	    DB::table('utility_account_types')->insert(
	    	array(
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'account_type' => 'Account Number'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'account_type' => 'Service Agreement ID'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'account_type' => 'Customer Number'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'account_type' => 'Pod ID'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'account_type' => 'Service Delivery Identifier'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'account_type' => 'Service Number'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'account_type' => 'Meter Number'
		        ),
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
		Schema::drop('utility_account_types');
	}

}
