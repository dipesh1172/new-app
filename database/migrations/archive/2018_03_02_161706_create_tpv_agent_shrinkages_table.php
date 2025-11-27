<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTpvAgentShrinkagesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tpv_agent_shrinkages', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('user_id');
			$table->integer('shrinkage_type');
			$table->integer('range_start');
			$table->integer('range_end');
			$table->integer('entered_by');
			$table->boolean('approved')->default(0);
			$table->integer('approved_by')->nullable();
			$table->integer('points')->nullable();
			$table->date('shrinkage_at')->nullable();
			$table->date('shrinkage_date')->nullable();
			$table->integer('reason_id')->nullable();
			$table->text('comment', 65535)->nullable();
			$table->softDeletes();
			$table->boolean('sverified')->default(0);
			$table->string('request_id', 10)->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tpv_agent_shrinkages');
	}

}
