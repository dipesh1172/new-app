<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUserAddressesAndEventHrtpv extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table(
            'users',
            function (Blueprint $table) {
                $table->timestamp('dob')->nullable();
                $table->string('ssn', 128)->nullable();
                $table->string('address', 128)->nullable();
                $table->string('city', 64)->nullable();
                $table->integer('state_id')->nullable();
                $table->string('zip', 8)->nullable();
            }
        );

        Schema::table(
            'events',
            function (Blueprint $table) {
                $table->tinyInteger('hrtpv')->default(0);
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
            'users',
            function (Blueprint $table) {
                $table->dropColumn('dob');
                $table->dropColumn('ssn');
                $table->dropColumn('address');
                $table->dropColumn('city');
                $table->dropColumn('state_id');
                $table->dropColumn('zip');
            }
        );

        Schema::table(
            'events',
            function (Blueprint $table) {
                $table->dropColumn('hrtpv');
            }
        );
    }
}
