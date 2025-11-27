<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameContactVectorToContactVectorIdInUserClientAlertOptinsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_client_alert_optins', function (Blueprint $table) {
            $table->renameColumn('contact_vector', 'contact_vector_id');
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
            $table->renameColumn('contact_vector_id', 'contact_vector');
        });
    }
}
