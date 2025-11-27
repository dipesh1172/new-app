<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEsiidToFeeschedule extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('invoice_rate_card', function (Blueprint $table) {
            $table->float('esiid_lookup')->after('pay_submission')->default(0.05);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
    }
}
