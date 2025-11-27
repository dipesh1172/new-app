<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexToPhoneNumberVoipLookup extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('phone_number_voip_lookup', function (Blueprint $table) {
            $table->index('phone_number_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('phone_number_voip_lookup', function (Blueprint $table) {
            $table->dropIndex(['phone_number_id']);
        });
    }
}
