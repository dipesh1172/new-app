<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTpvAgentShrinkageTypesTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tpv_agent_shrinkage_types', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->string('name', 191);
			$table->string('description', 191);
			$table->boolean('pointable')->default(1);
			$table->integer('default_points')->default(0);
			$table->string('shift_indicator', 1)->nullable();
			$table->softDeletes();
			$table->boolean('only_automatically_assigned')->default(0);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tpv_agent_shrinkage_types');
	}

}
