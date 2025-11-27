<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class CreateBusinessRulesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('business_rules', function(Blueprint $table)
		{
			$table->increments('id');
			$table->timestamps();
			$table->softDeletes()->index('idx_business_rules_deleted_at');
			$table->string('slug', 48)->nullable();
			$table->text('business_rule', 65535)->nullable();
			$table->text('answers', 65535)->nullable();
		});

		$dt = new DateTime;

	    DB::table('business_rules')->insert(
	    	array(
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'slug' => 'outbound_call_wait',
		            'business_rule' => "After a customer lead has been created in the TPV platform; how long (in seconds) should we wait before performing the outbound call?"
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'slug' => 'customer_no_response_wait',
		            'business_rule' => "During a customer call; what will be the hold time for no response from the customer?"
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'slug' => 'sales_agent_no_response_wait',
		            'business_rule' => "During a sales agent call; what will be the hold time for no response from the sales agent?"
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'slug' => 'customer_still_onpremise',
		            'business_rule' => 'If a sales agent has not left the customers premises, the sale will be dispositioned as a "No Sale" automatically.'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'slug' => 'validate_addresses',
		            'business_rule' => 'After a customer lead has been created in the TPV platform; the provided service and billing (if applicable) address(es) will be validated against an address database for both verification and proper formatting. Note: enabling this feature will include a $0.03 charge (per address verification) on your invoice.'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'slug' => 'customer_off_list',
		            'business_rule' => 'If the provided customer lead address is "off list", the sale will be dispositioned as a "No Sale" automatically.'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'slug' => 'standard_no_response_rebuttal',
		            'business_rule' => 'Standard rebuttal due to no response?'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'slug' => 'sales_agent_fraud',
		            'business_rule' => 'If the TPV agent suspects that Sales Agent is acting as the customer or that the customer is a child; the TPV will continue, but will automatically be dispositioned as "No Sale". The call is then sent for review by the TPV QA Department and a notification is sent of the "No Sale" status.'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'slug' => 'disposition_timeout',
		            'business_rule' => 'If a TPV call is dispositioned as "No Sale"; how long (in seconds) will the TPV agent have to provide a reason before a timeout occurs?'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'slug' => 'customer_history_recent_good_sale',
		            'business_rule' => 'If a good sale has been detected, for a specific customer, in the specified timeframe, the sale will be dispositioned as a "No Sale" automatically.'
		        ),
		        array(
		        	'created_at' => $dt->format('Y-m-d H:i:s'),
		        	'updated_at' => $dt->format('Y-m-d H:i:s'),
		            'slug' => 'customer_history_three_no_sales',
		            'business_rule' => 'If 3 "No Sales" have been detected for a specific customer, in the specified timeframe, the sale will be dispositioned as a "No Sale" automatically.'
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
		Schema::drop('business_rules');
	}

}
