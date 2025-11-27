<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCommodityToEztpvDisclosures extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('eztpv_disclosures', function (Blueprint $table) {
            $table->string('commodity')->after('channel_id')->default('both');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('eztpv_disclosures', function (Blueprint $table) {
            $table->dropColumn('commodity');
        });
    }
}
