<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTpvAgentShrinkageTypeReasonsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tpv_agent_shrinkage_type_reasons', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('parent');
			$table->string('description', 191);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tpv_agent_shrinkage_type_reasons');
	}

}
