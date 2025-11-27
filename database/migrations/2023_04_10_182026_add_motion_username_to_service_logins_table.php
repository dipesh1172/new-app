<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddMotionUsernameToServiceLoginsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('service_logins', function (Blueprint $table) {
            $table->string('motion_username', 200)->nullable();
            $table->index('motion_username');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('service_logins', function (Blueprint $table) {
            $table->dropColumn('motion_username');
        });
    }
}
