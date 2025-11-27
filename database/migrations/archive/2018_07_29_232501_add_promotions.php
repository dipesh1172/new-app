<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPromotions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'brand_promotions',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('brand_id', 36);
                $table->string('utility_id', 36)->nullable();
                $table->string('product_id', 36)->nullable();
                $table->string('rate_id', 36)->nullable();
                $table->integer('channel_id')->nullable();
                $table->integer('market_id')->nullable();
                $table->integer('state_id')->nullable();
                $table->string('name', 36)->nullable();
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
        Schema::dropIfExists('brand_promotions');
    }
}
