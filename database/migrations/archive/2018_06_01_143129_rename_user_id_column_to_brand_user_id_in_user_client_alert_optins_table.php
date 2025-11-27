<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class RenameUserIdColumnToBrandUserIdInUserClientAlertOptinsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('user_client_alert_optins', function (Blueprint $table) {
            $table->renameColumn('user_id', 'brand_user_id');
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
            $table->renameColumn('brand_user_id', 'user_id');
        });
    }
}
