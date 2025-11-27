<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSubtypeToPhoneNumberVoipLookup extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('phone_number_voip_lookup', function (Blueprint $table) {
            $table->string('phone_number_subtype', 30);
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
            $table->dropColumn('phone_number_subtype');
        });
    }
}
