<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddContractsTermsAndConditions extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('contract_config_tcs', function (Blueprint $table) {
            $table->string('id', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_id', 36)->nullable();
            $table->string('name', 255)->nullable();
            $table->string('upload_id', 36)->nullable();
            $table->text('fdf')->nullable();
        });

        Schema::create('contract_config_cancellations', function (Blueprint $table) {
            $table->string('id', 36)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->string('brand_id', 36)->nullable();
            $table->string('name', 255)->nullable();
            $table->text('content')->nullable();
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
