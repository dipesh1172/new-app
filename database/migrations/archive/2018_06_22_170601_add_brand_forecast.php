<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBrandForecast extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
		Schema::create('brand_forecast', function(Blueprint $table)
		{
			$table->string('id', 36)->primary();
			$table->timestamps();
			$table->softDeletes();
			$table->timestamp('start_date')->nullable();
			$table->timestamp('end_date')->nullable();
			$table->string('brand_id', 36)->nullable();
			$table->string('brand_user_id', 36)->nullable();
			$table->integer('expected_sales');
		});
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
