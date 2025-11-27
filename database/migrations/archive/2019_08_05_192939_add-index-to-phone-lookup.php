<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIndexToPhoneLookup extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('phone_number_lookup', function (Blueprint $table) {
            $table->index(['type_id', 'phone_number_type_id', 'phone_number_id'], 'phone_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('phone_number_lookup', function (Blueprint $table) {
            $table->dropIndex('phone_type_index');
        });
    }
}
