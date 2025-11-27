<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBrandServices extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('brand_services', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_id', 36);
            $table->string('brand_service_type_id', 36);
            $table->text('rate_card')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('brand_services');
    }
}
