<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddDistributionEmailFieldToBrandClientAlertsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('brand_client_alerts', function (Blueprint $table) {
            $table->text('distribution_email');
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
            $table->dropColumn('distribution_email');
        });
    }
}
