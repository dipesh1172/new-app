<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreateBillFrequenciesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('bill_frequencies', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			$table->softDeletes()->index('idx_bill_frequencies_deleted_at');
			$table->string('frequency', 16)->nullable();
		});

		$dt = new DateTime;

	    DB::table('bill_frequencies')->insert(
	    	array(
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'frequency' => 'bi-monthly',
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'frequency' => 'monthly',
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'frequency' => 'weekly',
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
		Schema::drop('bill_frequencies');
	}

}
