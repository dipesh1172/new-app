<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RemoveColumnsFromUserClientAlertOptinsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_client_alert_optins', function (Blueprint $table) {
            $table->dropColumn('email');
            $table->dropColumn('sms');
            $table->dropColumn('popup');
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
            $table->integer('email');
            $table->integer('sms');
            $table->integer('popup');
        });
    }
}
