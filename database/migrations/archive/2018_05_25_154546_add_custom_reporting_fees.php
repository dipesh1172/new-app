<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddCustomReportingFees extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'invoice_rate_card', 
            function (Blueprint $table) {
                $table->double('custom_report_fee', 10, 4)
                    ->nullable()->default(null);
            }
        );
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table(
            'invoice_rate_card', 
            function (Blueprint $table) {
                $table->dropColumn('custom_report_fee');
            }
        );
    }
}
