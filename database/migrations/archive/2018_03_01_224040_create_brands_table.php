<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBrandsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('brands', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->string('client_id', 36)->nullable();
			$table->string('name', 64)->nullable();
			$table->string('logo_path')->nullable();
			$table->string('service_number', 20)->nullable();
			$table->string('address', 64)->nullable();
			$table->string('city', 64)->nullable();
			$table->integer('state')->nullable();
			$table->string('zip', 8)->nullable();
			$table->string('dxc_table_name', 64)->nullable();
			$table->boolean('allow_bg_checks')->default(0);
			$table->integer('active')->nullable()->default(1);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('brands');
	}

}
