<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenamePromotionsDescriptionColumnToName extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_promotions', function (Blueprint $table) {
            $table->renameColumn('promotion_description', 'name');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_promotions', function (Blueprint $table) {
            $table->renameColumn('name', 'promotion_description');
        });
    }
}
