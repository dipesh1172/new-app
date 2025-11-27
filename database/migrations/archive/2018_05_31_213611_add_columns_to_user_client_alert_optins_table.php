<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnsToUserClientAlertOptinsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_client_alert_optins', function (Blueprint $table) {
            $table->string('contact_method');
            $table->string('contact_vector');
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
            $table->dropColumn('contact_method');
            $table->dropColumn('contact_vector');
        });
    }
}
