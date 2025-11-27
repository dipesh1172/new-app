<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddUndeliverableColumnToEmailAddressesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('email_addresses', function (Blueprint $table) {
            $table->tinyInteger('undeliverable')->defaul(0);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('email_addresses', function (Blueprint $table) {
            $table->dropColumn('undeliverable');
        });
    }
}
