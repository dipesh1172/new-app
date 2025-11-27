<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRatePromoCode extends Migration
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
                $table->string('rate_promo_code', 32)
                    ->nullable()->after('program_code');
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
                $table->removeColumn('rate_promo_code');
            }
        );
    }
}
