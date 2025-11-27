<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class ModifyUserClientAlertOptinsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_client_alert_optins', function (Blueprint $table) {
            $table->renameColumn('brand_user_id', 'user_id');
            $table->renameColumn('client_alert_id', 'brand_client_alert_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('user_client_alert_optins', function (Blueprint $table) {
            $table->renameColumn('user_id', 'brand_user_id');
            $table->renameColumn('brand_client_alert_id', 'client_alert_id');
        });
    }
}
