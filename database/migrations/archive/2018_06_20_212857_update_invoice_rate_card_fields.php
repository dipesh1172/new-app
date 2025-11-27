<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class UpdateInvoiceRateCardFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoice_rate_card', function (Blueprint $table) {
            $table->decimal('eztpv_tm_rate', 10)->nullable();
            $table->decimal('eztpv_contract', 10)->nullable();
            $table->decimal('eztpv_sms', 10)->nullable();
            $table->renameColumn('eztpv_tm_flat', 'eztpv_tm_monthly');
            $table->dropColumn('eztpv_plus_rate');
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
            $table->dropColumn('eztpv_tm_rate');
            $table->dropColumn('eztpv_contract');
            $table->dropColumn('eztpv_sms');
            $table->renameColumn('eztpv_tm_monthly', 'eztpv_tm_flat');
            $table->decimal('eztpv_plus_rate', 10)->nullable();
        });
    }
}
