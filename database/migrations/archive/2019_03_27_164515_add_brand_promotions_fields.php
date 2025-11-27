<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBrandPromotionsFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'brand_promotions',
            function (Blueprint $table) {
                $table->tinyInteger('promotion_type')->nullable();
                $table->string('promotion_code', 50)->nullable();
                $table->string('promotion_key', 50)->nullable();
                $table->integer('reward')->nullable();
            }
        );
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
