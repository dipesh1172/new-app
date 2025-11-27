<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateInvoiceRateCardTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('invoice_rate_card', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('brand_id', 36)->nullable();
			$table->integer('bill_frequency_id')->unsigned()->nullable()->index('idx_invoice_rate_card_bill_frequency_id');
			$table->integer('bill_methodology_id')->unsigned()->nullable()->index('idx_invoice_rate_card_bill_methodology_id');
			$table->integer('term_days')->nullable()->index('idx_invoice_rate_card_term_days');
			$table->integer('minimum')->nullable()->index('idx_invoice_rate_card_minimum');
			$table->text('levels', 65535)->nullable();
			$table->decimal('live_flat_rate', 10)->nullable();
			$table->decimal('it_billable', 10)->nullable();
			$table->decimal('qa_billable', 10)->nullable();
			$table->decimal('cs_billable', 10)->nullable();
			$table->decimal('eztpv_rate', 10)->nullable();
			$table->decimal('eztpv_plus_rate', 10)->nullable();
			$table->decimal('eztpv_tm_flat', 10)->nullable();
			$table->decimal('did_tollfree', 10)->nullable();
			$table->decimal('did_local', 10)->nullable();
			$table->decimal('address_verification_rate', 10)->nullable();
			$table->decimal('cell_number_verification', 10)->nullable();
			$table->decimal('ld_billback_intl', 10)->nullable();
			$table->decimal('ld_billback_dom', 10)->nullable();
			$table->decimal('ld_billback', 10)->nullable();
			$table->decimal('ivr_rate', 10)->nullable();
			$table->decimal('ivr_trans_rate', 10)->nullable();
			$table->decimal('storage_rate_in_gb', 10)->nullable();
			$table->decimal('storage_in_gb_min', 10)->nullable();
			$table->decimal('contract_review', 10)->nullable();
			$table->decimal('server_hosting_monthly', 10)->nullable();
			$table->decimal('late_fee', 10)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('invoice_rate_card');
	}

}
