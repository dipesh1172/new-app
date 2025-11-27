<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddProductFields extends Migration
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
            function (Blueprint $table) {
                $table->float('monthly_fee', 10, 0)->after('daily_fee');
                $table->string('promo_code', 32)->after('monthly_fee');
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
            'products', function (Blueprint $table) {
                $table->removeColumn('monthly_fee');
                $table->removeColumn('promo_code');
            }
        );
    }
}
