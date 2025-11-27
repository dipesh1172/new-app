<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRatesCancellationCurrencies extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'rates',
            function (Blueprint $table) {
                $table->integer('cancellation_fee_currency')
                    ->after('cancellation_fee')->nullable();
                $table->integer('intro_cancellation_fee_currency')
                    ->after('intro_cancellation_fee')->nullable();
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
            'rates',
            function (Blueprint $table) {
                $table->dropColumn('cancellation_fee_currency');
                $table->dropColumn('intro_cancellation_fee');
            }
        );
    }
}
