<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRateAndStatFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'products',
            function ($table) {
                $table->float('transaction_fee', 10, 4)->nullable();
                $table->integer('transaction_fee_currency_id')->nullable();
            }
        );

        Schema::table(
            'stats_product',
            function ($table) {
                $table->text('recording')->nullable();
                $table->text('contracts')->nullable();
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
            'products',
            function ($table) {
                $table->dropColumn('transaction_fee');
                $table->dropColumn('transaction_fee_currency_id');
            }
        );

        Schema::table(
            'stats_product',
            function ($table) {
                $table->dropColumn('recording');
                $table->dropColumn('contracts');
            }
        );
    }
}
