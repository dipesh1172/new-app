<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRateLevelBrandsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rate_level_brands', function (Blueprint $table) {
            $table->string('id', 36)->unique();
            $table->timestamps();
            $table->softDeletes();
            $table->string('created_by', 36)->nullable();
            $table->string('deleted_by', 36)->nullabel();
            $table->string('brand_id', 36)->nullable();
            $table->text('brand_names')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rate_level_brands');
    }
}
