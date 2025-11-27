<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateLocalityRestrictionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('locality_restrictions', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_id', 36);
            $table->string('restrict', 6);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('locality_restrictions');
    }
}
