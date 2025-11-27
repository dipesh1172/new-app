<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveAcctNumLocFromUtility extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('utilities', function (Blueprint $table) {
            $table->dropColumn('acct_num_bill_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('utilities', function (Blueprint $table) {
            // there is no down, only Zuul
        });
    }
}
