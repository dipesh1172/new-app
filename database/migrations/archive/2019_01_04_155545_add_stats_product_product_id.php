<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddStatsProductProductId extends Migration
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
                $table->string('rate_id', 36)->after('service_country')->nullable();
                $table->string('product_id', 36)
                    ->after('rate_channel_source')->nullable();
                $table->string('utility_id', 36)
                    ->after('event_product_id')->nullable();
                $table->string('utility_supported_fuel_id', 36)
                    ->after('utility_id')->nullable();
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
                $table->dropColumn('rate_id');
                $table->dropColumn('product_id');
                $table->dropColumn('utility_id');
                $table->dropColumn('utility_supported_fuel_id');
            }
        );
    }
}
