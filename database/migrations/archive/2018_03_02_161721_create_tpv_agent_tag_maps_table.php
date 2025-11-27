<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTpvAgentTagMapsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tpv_agent_tag_maps', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->integer('user_id');
			$table->integer('tag_id');
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tpv_agent_tag_maps');
	}

}
