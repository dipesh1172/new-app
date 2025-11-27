<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCommodityColumnToTermsAndConditionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('terms_and_conditions', function (Blueprint $table) {
            $table->string('commodity', 128)->nullable()->after('rate_type_id');
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
            $table->dropColumn('commodity');
        });
    }
}
