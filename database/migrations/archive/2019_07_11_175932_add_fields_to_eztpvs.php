<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddFieldsToEztpvs extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('eztpvs', function (Blueprint $table) {
            $table->tinyInteger('live_call')->nullable();
            $table->tinyInteger('photo')->nullable();
            $table->tinyInteger('forced_phone_validation')->nullable();
            $table->tinyInteger('has_digital')->nullable();
            $table->tinyInteger('contract_type')->nullable();
            $table->string('digital_delivery', 16)->nullable();
            $table->string('eztpv_contract_delivery', 16)->nullable();
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
