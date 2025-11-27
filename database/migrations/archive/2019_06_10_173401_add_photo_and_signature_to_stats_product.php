<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPhotoAndSignatureToStatsProduct extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stats_product', function (Blueprint $table) {
            $table->text('photos')->after('contracts')->nullable();
            $table->text('signature_pages')->after('photos')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stats_product', function (Blueprint $table) {
            //
        });
    }
}
