<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddAddressAndWebsiteColumnsToUtilitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('utilities', function (Blueprint $table) {
            $table->string('address1')->after('customer_service')->nullable();
            $table->string('address2')->after('address1')->nullable();
            $table->string('address3')->after('address2')->nullable();
            $table->string('city')->after('address3')->nullable();
            $table->string('state')->after('city')->nullable();
            $table->string('zip')->after('state')->nullable();
            $table->string('country')->after('zip')->nullable();
            $table->string('website')->after('country')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('utilities', function (Blueprint $table) {
            $table->dropColumn('address1');
            $table->dropColumn('address2');
            $table->dropColumn('address3');
            $table->dropColumn('city');
            $table->dropColumn('state');
            $table->dropColumn('zip');
            $table->dropColumn('country');
            $table->dropColumn('website');
        });
    }
}
