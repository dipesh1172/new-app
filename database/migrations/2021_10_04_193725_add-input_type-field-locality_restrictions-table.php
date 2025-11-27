<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddInputTypeFieldLocalityRestrictionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('locality_restrictions', function (Blueprint $table) {
            $table->tinyInteger('input_type')->unsigned()->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('locality_restrictions', function (Blueprint $table) {
            $table->dropColumn(['input_type']);
        });
    }
}
