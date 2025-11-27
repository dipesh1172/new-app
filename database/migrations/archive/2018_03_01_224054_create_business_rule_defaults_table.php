<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreateBusinessRuleDefaultsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('business_rule_defaults', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			$table->softDeletes()->index('idx_business_rule_defaults_deleted_at');
			$table->string('business_rule_id', 36)->nullable();
			$table->text('default_answer', 65535)->nullable();
		});

		$dt = new DateTime;

	    DB::table('business_rule_defaults')->insert(
	        array(
	        	'created_at' => $dt->format('Y-m-d H:i:s'),
	        	'updated_at' => $dt->format('Y-m-d H:i:s'),
	            'business_rule_id' => 1,
	            'default_answer' => "30"
	        ),
	        array(
	        	'created_at' => $dt->format('Y-m-d H:i:s'),
	        	'updated_at' => $dt->format('Y-m-d H:i:s'),
	            'business_rule_id' => 2,
	            'default_answer' => "30"
	        ),
	        array(
	        	'created_at' => $dt->format('Y-m-d H:i:s'),
	        	'updated_at' => $dt->format('Y-m-d H:i:s'),
	            'business_rule_id' => 3,
	            'default_answer' => "30"
	        ),
	        array(
	        	'created_at' => $dt->format('Y-m-d H:i:s'),
	        	'updated_at' => $dt->format('Y-m-d H:i:s'),
	            'business_rule_id' => 4,
	            'default_answer' => "on"
	        ),
	        array(
	        	'created_at' => $dt->format('Y-m-d H:i:s'),
	        	'updated_at' => $dt->format('Y-m-d H:i:s'),
	            'business_rule_id' => 5,
	            'default_answer' => "on"
	        ),
	        array(
	        	'created_at' => $dt->format('Y-m-d H:i:s'),
	        	'updated_at' => $dt->format('Y-m-d H:i:s'),
	            'business_rule_id' => 6,
	            'default_answer' => "on"
	        ),
	        array(
	        	'created_at' => $dt->format('Y-m-d H:i:s'),
	        	'updated_at' => $dt->format('Y-m-d H:i:s'),
	            'business_rule_id' => 7,
	            'default_answer' => "I am unable to hear you. Due to no response, I will be ending the call at this time."
	        ),
	        array(
	        	'created_at' => $dt->format('Y-m-d H:i:s'),
	        	'updated_at' => $dt->format('Y-m-d H:i:s'),
	            'business_rule_id' => 8,
	            'default_answer' => "on"
	        ),
	        array(
	        	'created_at' => $dt->format('Y-m-d H:i:s'),
	        	'updated_at' => $dt->format('Y-m-d H:i:s'),
	            'business_rule_id' => 9,
	            'default_answer' => "30"
	        ),
	        array(
	        	'created_at' => $dt->format('Y-m-d H:i:s'),
	        	'updated_at' => $dt->format('Y-m-d H:i:s'),
	            'business_rule_id' => 10,
	            'default_answer' => "on"
	        ),
	        array(
	        	'created_at' => $dt->format('Y-m-d H:i:s'),
	        	'updated_at' => $dt->format('Y-m-d H:i:s'),
	            'business_rule_id' => 11,
	            'default_answer' => "on"
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
		Schema::drop('business_rule_defaults');
	}

}
