<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLanguageIdToBrandEztpvContractsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_eztpv_contracts', function (Blueprint $table) {
            $table->integer('language_id')->after('channel_id')->default(1);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_eztpv_contracts', function (Blueprint $table) {
            $table->dropColumn('language_id');
        });
    }
}
