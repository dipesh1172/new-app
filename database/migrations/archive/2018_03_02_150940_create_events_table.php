<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateEventsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('events', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('brand_id', 36);
			$table->string('confirmation_code', 24)->nullable();
			$table->integer('contact_type_id')->nullable();
			$table->integer('channel_id')->nullable();
			$table->integer('country_id')->nullable();
			$table->string('vendor_id', 36)->nullable();
			$table->string('office_id', 36)->nullable();
			$table->string('script_id', 36)->nullable();
			$table->integer('event_source_id')->nullable();
			$table->integer('language_id')->unsigned()->nullable();
			$table->string('phone_number', 24)->nullable();
			$table->string('email_address', 128)->nullable();
			$table->string('disposition_id', 36)->nullable();
			$table->string('sales_agent_id', 36)->nullable();
			$table->integer('event_results_id')->unsigned()->nullable();
			$table->string('station_id', 36)->nullable();
			$table->string('dxc_rec_id', 11)->nullable();
			$table->string('eztpv_id', 36)->nullable();
            $table->integer('ip_addr', 4)->unsigned()->nullable();
            $table->string('gps_coords', 16)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('events');
	}

}
