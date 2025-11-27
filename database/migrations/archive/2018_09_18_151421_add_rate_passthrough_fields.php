<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRatePassthroughFields extends Migration
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
                $table->string('rate_source_code', 128)
                    ->nullable()->after('rate_promo_code');
                $table->string('rate_renewal_plan', 128)
                    ->nullable()->after('rate_source_code');
                $table->string('rate_channel_source', 24)
                    ->nullable()->after('rate_renewal_plan');
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
                $table->removeColumn('rate_source_code');
                $table->removeColumn('rate_renewal_plan');
                $table->removeColumn('rate_channel_source');
            }
        );
    }
}
