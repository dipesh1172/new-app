<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTpvAssetTagsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('tpv_asset_tags', function(Blueprint $table)
		{
			$table->integer('id', true);
			$table->timestamps();
			$table->softDeletes();
			$table->integer('asset_type');
			$table->string('description', 191);
			$table->string('model', 191);
			$table->string('make', 191);
			$table->string('serial', 191)->nullable();
			$table->string('extra_identifier', 191)->nullable();
			$table->date('purchase_date')->nullable();
			$table->string('purchase_cost', 191)->nullable();
			$table->string('ponumber', 191)->nullable();
			$table->string('asset_id', 20);
			$table->integer('user_id')->nullable();
			$table->boolean('missing')->default(0);
			$table->boolean('checked_out')->default(0);
		});
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('tpv_asset_tags');
	}

}
