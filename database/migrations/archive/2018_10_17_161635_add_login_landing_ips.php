<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddLoginLandingIps extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create(
            'login_landing_ips',
            function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->timestamps();
                $table->softDeletes();
                $table->string('login_landing_id', 36)->nullable();
                $table->string('ip', 64);
                $table->string('description', 255)->nullable();
            }
        );

        Schema::table(
            'login_landing',
            function (Blueprint $table) {
                $table->tinyInteger('self_onboard')->default(0);
                $table->dropColumn('ip_addrs');
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
        Schema::dropIfExists('login_landing_ips');

        Schema::table(
            'login_landing',
            function (Blueprint $table) {
                $table->dropColumn('self_onboard');
                $table->text('ip_addrs')->nullable();
            }
        );
    }
}
