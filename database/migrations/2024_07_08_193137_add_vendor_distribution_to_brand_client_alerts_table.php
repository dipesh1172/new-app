<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddVendorDistributionToBrandClientAlertsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_client_alerts', function (Blueprint $table) {
            $table->text('vendor_distribution')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('brand_client_alerts', function (Blueprint $table) {
            $table->dropColumn('vendor_distribution');
        });
    }
}
