<?php

use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddCarrierLookupRvpInvoiceableType extends Migration
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
                    'id' => 4,
                    'desc' => 'RealValidation FraudCheck Lookup',
                    'resource' => 'RealValidation::FraudCheck',
                    'created_at' => $now,
                    'updated_at' => $now,
                    'standard_rate' => 4,
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
