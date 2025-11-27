<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBrandUtilitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('brand_utilities', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->timestamps();
            $table->string('brand_id', 36);
            $table->string('utility_id', 36);
            $table->string('utility_label', 36);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('brand_utilities');
    }
}
