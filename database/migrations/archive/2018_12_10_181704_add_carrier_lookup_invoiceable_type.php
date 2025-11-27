<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Carbon\Carbon;

class AddCarrierLookupInvoiceableType extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $now = Carbon::now();
        DB::table('invoiceable_types')->insert(
            [
                [
                    'id' => 2,
                    'desc' => 'Twilio Carrier Lookup',
                    'resource' => 'Twilio::Carrier',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'standard_rate' => 5,
                    'currency_id' => 1,
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
