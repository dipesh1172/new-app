<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateEventProductIdentifiersUanTypeColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('event_product_identifiers', function (Blueprint $table) {
            $table->tinyInteger('utility_account_number_type_id')->unsigned()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('event_product_identifiers', function (Blueprint $table) {
            $table->dropColumn('utility_account_number_type_id');
        });
    }
}
