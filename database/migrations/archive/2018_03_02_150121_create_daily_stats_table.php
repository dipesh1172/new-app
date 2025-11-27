<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateDailyStatsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('daily_stats', function(Blueprint $table)
		{
			$table->string('id', 36)->default('')->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->date('stats_date')->nullable();
			$table->string('brand_id', 36)->nullable();
			$table->integer('total_records')->nullable();
			$table->decimal('total_live_min', 10)->nullable();
			$table->decimal('total_live_inbound_min', 10)->nullable();
			$table->decimal('total_live_outbound_min', 10)->nullable();
			$table->integer('live_english_min')->nullable();
			$table->integer('live_spanish_min')->nullable();
			$table->integer('live_good_sale')->nullable();
			$table->integer('live_no_sale')->nullable();
			$table->integer('live_channel_dtd')->nullable();
			$table->integer('live_channel_tm')->nullable();
			$table->integer('live_cc_tulsa_min')->nullable();
			$table->integer('live_cc_tahlequah_min')->nullable();
			$table->integer('live_cc_lasvegas_min')->nullable();
			$table->decimal('total_ivr_min', 10)->nullable();
			$table->decimal('total_ivr_inbound_min', 10)->nullable();
			$table->decimal('total_ivr_outbound_min', 10)->nullable();
			$table->integer('dnis_tollfree')->nullable();
			$table->integer('dnis_local')->nullable();
			$table->integer('eztpv_basic')->nullable();
			$table->integer('eztpv_plus')->nullable();
			$table->decimal('ld_dom', 10)->nullable();
			$table->decimal('ld_intl', 10)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('daily_stats');
	}

}
