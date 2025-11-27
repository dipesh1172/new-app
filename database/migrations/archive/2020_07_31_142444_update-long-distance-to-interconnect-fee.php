<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateLongDistanceToInterconnectFee extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::table('invoice_desc')->where('item_desc', 'Long Distance (domestic)')->update(['item_desc' => 'Interconnect Fee (domestic)']);
        DB::table('invoice_desc')->where('item_desc', 'Long Distance (international)')->update(['item_desc' => 'Interconnect Fee (international)']);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // There is no down, only Zuul
    }
}
