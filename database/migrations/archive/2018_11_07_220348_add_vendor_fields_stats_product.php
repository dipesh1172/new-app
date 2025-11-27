<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVendorFieldsStatsProduct extends Migration
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
                $table->string('vendor_label', 36)->nullable()
                    ->after('vendor_name');
                $table->string('vendor_code', 32)->nullable()
                    ->after('vendor_label');
                $table->integer('vendor_grp_id')->nullable()
                    ->after('vendor_code');                    
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
                $table->dropColumn('vendor_label');
                $table->dropColumn('vendor_code');
                $table->dropColumn('vendor_grp_id');
            }
        );
    }
}
