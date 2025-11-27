<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class CreateEmailInvoiceableType extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('invoiceable_types')->insert([
            'id' => 3,
            'created_at' => $now,
            'updated_at' => $now,
            'desc' => 'Electronic Mail',
            'resource' => 'Email::Send',
            'standard_rate' => 0,
            'currency_id' => 1,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // There is no down, only Zuul!
    }
}
