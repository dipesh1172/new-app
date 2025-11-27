<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEztpvSaleTypeDescription extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'eztpv_sale_types', function (Blueprint $table) {
                $table->text('description');
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
            'eztpv_sale_types',
            function (Blueprint $table) {
                $table->dropColumn('page_size');
            }
        );
    }
}
