<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddIntroRateToRates extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'rates', function (Blueprint $table) {
                $table->double('intro_rate_amount', 10, 4)
                    ->nullable()->default(null);

                $table->double('intro_cancellation_fee', 10, 4)
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
            'rates', function (Blueprint $table) {
                $table->dropColumn(
                    [
                        'intro_rate_amount',
                        'intro_cancellation_fee',
                    ]
                );
            }
        );
    }
}
