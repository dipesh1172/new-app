<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddSuggestedAddressFieldsToAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->string('suggested_line_1')->nullable();
            $table->string('suggested_line_2')->nullable();
            $table->string('suggested_city')->nullable();
            $table->string('suggested_state_province')->nullable();
            $table->string('suggested_zip')->nullable();
            $table->integer('suggested_country_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('addresses', function (Blueprint $table) {
            $table->dropColumn('suggested_line_1');
            $table->dropColumn('suggested_line_2');
            $table->dropColumn('suggested_city');
            $table->dropColumn('suggested_state_province');
            $table->dropColumn('suggested_zip');
            $table->dropColumn('suggested_country_id');
        });
    }
}
