<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHrtpvFees extends Migration
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
                $table->double('hrtpv_transaction')->nullable();
                $table->double('hrtpv_document')->nullable();
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
                $table->dropColumn('hrtpv_transaction');
                $table->dropColumn('hrtpv_document');
            }
        );
    }
}
