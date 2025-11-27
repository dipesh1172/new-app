<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class FixInvoiceableTypesResourceLength extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('invoiceable_types', function (Blueprint $table) {
            $table->string('resource', 40)->change();
        });

        DB::table('invoiceable_types')->where('resource', 'RealValidation::Frau')->update(['resource' => 'RealValidation::FraudCheck']);
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        // There is no down, only Zuul
    }
}
