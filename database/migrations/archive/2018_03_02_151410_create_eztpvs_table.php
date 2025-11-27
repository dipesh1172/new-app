<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateEztpvsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('eztpvs', function(Blueprint $table)
		{
			$table->string('id', 36)->default('')->primary();
			$table->timestamps();
			$table->softDeletes()->index('idx_ez_tpv_deleted_at');
			$table->string('brand_id', 36)->nullable();
			$table->string('user_id', 36)->nullable();
			$table->text('form_data', 65535)->nullable();
			$table->integer('processed', 2)->default(0);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('eztpvs');
	}

}
