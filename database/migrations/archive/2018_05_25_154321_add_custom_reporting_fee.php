<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

class AddCustomReportingFee extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('invoice_desc')->insert(
            [
                [
                    'id' => 12,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'item_desc' => 'Custom Reports',
                ],
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
