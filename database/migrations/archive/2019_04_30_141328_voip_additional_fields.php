<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class VoipAdditionalFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'phone_number_voip_lookup',
            function (Blueprint $table) {
                $table->string('carrier', 64)->after('phone_number_id')->nullable();
                $table->string('phone_number_type', 64)->after('carrier')->nullable();
                $table->string('phone_number_lookup_name', 128)->after('phone_number_type')->nullable();
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
