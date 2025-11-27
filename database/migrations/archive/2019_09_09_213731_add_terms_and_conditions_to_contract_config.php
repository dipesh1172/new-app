<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTermsAndConditionsToContractConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('contract_config', function (Blueprint $table) {
            $table->string('terms_and_conditions', 36)->nullable();
            $table->string('terms_and_conditions_name', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('contract_config', function (Blueprint $table) {
            //
        });
    }
}
