<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddPromotionIdToEventProducts extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'event_product',
            function (Blueprint $table) {
                $table->string('brand_promotion_id', 36)->nullable();
            }
        );

        Schema::table(
            'events',
            function (Blueprint $table) {
                $table->dropColumn('brand_promotion_id');
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
            'event',
            function (Blueprint $table) {
                $table->string('brand_promotion_id', 36)->nullable();
            }
        );

        Schema::table(
            'event_product',
            function (Blueprint $table) {
                $table->dropColumn('brand_promotion_id');
            }
        );
    }
}
