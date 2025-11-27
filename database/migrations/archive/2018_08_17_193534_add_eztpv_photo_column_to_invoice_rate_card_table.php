<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEztpvPhotoColumnToInvoiceRateCardTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoice_rate_card', function (Blueprint $table) {
            $table->decimal('eztpv_photo', 10, 2)->nullable()->after('eztpv_contract');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoice_rate_card', function (Blueprint $table) {
            $table->removeColumn('eztpv_photo');
        });
    }
}
