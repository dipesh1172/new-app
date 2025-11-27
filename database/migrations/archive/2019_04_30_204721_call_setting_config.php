<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CallSettingConfig extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'brand_states',
            function (Blueprint $table) {
                $table->tinyInteger('call_setting_res_tm')->nullable();
                $table->tinyInteger('call_setting_res_dtd')->nullable();
                $table->tinyInteger('call_setting_sc_tm')->nullable();
                $table->tinyInteger('call_setting_sc_dtd')->nullable();
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
