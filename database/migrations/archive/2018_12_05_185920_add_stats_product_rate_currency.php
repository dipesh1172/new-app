<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatsProductRateCurrency extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'stats_product',
            function (Blueprint $table) {
                $table->string('product_rate_amount_currency', 16)
                    ->after('product_rate_amount')->nullable();

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
            'stats_product',
            function (Blueprint $table) {
                $table->dropColumn('product_rate_amount_currency');
            }
        );
    }
}
