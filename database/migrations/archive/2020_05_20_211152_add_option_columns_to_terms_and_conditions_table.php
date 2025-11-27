<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddOptionColumnsToTermsAndConditionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('terms_and_conditions', function (Blueprint $table) {
            $table->tinyInteger('hide_on_eztpv')->after('rate_type_id')->default(0);
            $table->tinyInteger('green_product')->after('hide_on_eztpv')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('terms_and_conditions', function (Blueprint $table) {
            $table->dropColumn('hide_on_eztpv');
            $table->dropColumn('green_product');
        });
    }
}
