<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBrandContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('brand_contracts', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamps();
            $table->string('client_id', 128);
            $table->string('brand_id', 128);
            $table->string('status', 128);
            $table->string('filename')->nullable();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('brand_contracts', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
}
