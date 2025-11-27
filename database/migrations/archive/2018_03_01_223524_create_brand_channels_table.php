<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBrandChannelsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('brand_channels', function(Blueprint $table)
		{
			$table->string('id', 36)->default('')->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('brand_id', 36)->nullable();
			$table->integer('channel_id')->nullable();
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('brand_channels');
	}

}
